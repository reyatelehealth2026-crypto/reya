-- Migration: Fix UNIQUE constraint for multi-platform support
-- 
-- Problem: The existing UNIQUE KEY `unique_line_user` on (line_account_id, line_user_id)
-- causes issues when multiple Facebook users are created because:
-- - Both have line_account_id = NULL
-- - MySQL treats multiple (NULL, value) as duplicates in UNIQUE constraints
--
-- Solution: Add a composite UNIQUE constraint on (platform, platform_user_id, facebook_account_id)
-- for Facebook users specifically.

-- Add UNIQUE constraint for Facebook users
ALTER TABLE users
ADD UNIQUE KEY unique_facebook_user (platform, platform_user_id, facebook_account_id);

-- Add UNIQUE constraint for TikTok users (future-proofing)
ALTER TABLE users
ADD UNIQUE KEY unique_tiktok_user (platform, platform_user_id, tiktok_account_id);
