# 🚀 Odoo Notification System - Deployment Checklist

## ✅ Pre-Deployment

### 1. Database Migration
- [ ] Upload `database/migration_odoo_notification_system.sql` to server
- [ ] Upload `install/run_odoo_notification_system_migration.php` to server
- [ ] Run migration: `https://your-domain.com/install/run_odoo_notification_system_migration.php`
- [ ] Verify all 5 tables created:
  - `odoo_notification_preferences`
  - `odoo_notification_queue`
  - `odoo_notification_log`
  - `odoo_notification_batch_groups`
  - `odoo_notification_templates`

### 2. Seed Data
- [ ] Upload `install/seed_notification_templates.php`
- [ ] Run seeder: `https://your-domain.com/install/seed_notification_templates.php`
- [ ] Verify default preferences loaded (14 event types for customer)

### 3. Core Classes
- [ ] Upload all classes to `classes/` folder:
  - `NotificationPreferencesManager.php`
  - `NotificationBatcher.php`
  - `NotificationQueue.php`
  - `NotificationLogger.php`
  - `NotificationRouter.php`
  - `RoadmapMessageBuilder.php`

### 4. API Endpoints
- [ ] Upload `api/notification-preferences.php`
- [ ] Upload `api/notification-queue-status.php`
- [ ] Test API: `curl https://your-domain.com/api/notification-queue-status.php`

### 5. Worker Setup
- [ ] Upload `worker/notification-worker.php`
- [ ] Set up cron job:
  ```bash
  * * * * * cd /path/to/re-ya && php worker/notification-worker.php >> logs/notification-worker.log 2>&1 &
  ```
- [ ] Create logs directory: `mkdir -p logs`
- [ ] Set permissions: `chmod 755 worker/notification-worker.php`

### 6. LIFF Setup (Optional)
- [ ] Upload `liff/notification-settings.php`
- [ ] Create LIFF app in LINE Developers Console
- [ ] Update LIFF ID in `liff/notification-settings.php`
- [ ] Test LIFF: Open in LINE app

## 🧪 Testing

### 1. Run Test Suite
- [ ] Upload `tests/test-notification-system.php`
- [ ] Run tests: `https://your-domain.com/tests/test-notification-system.php`
- [ ] All tests should pass ✅

### 2. Test Webhook Flow
- [ ] Send test webhook using `test-odoo-webhook.bat`
- [ ] Verify webhook logged in `odoo_webhooks_log`
- [ ] Verify notification queued in `odoo_notification_queue`
- [ ] Wait 5 seconds, check if notification sent
- [ ] Verify in `odoo_notification_log`

### 3. Test Roadmap Batching
- [ ] Send multiple events for same order:
  - `order.picking`
  - `order.packing`
  - `order.packed` (milestone)
- [ ] Verify batch group created
- [ ] Verify roadmap message sent when milestone reached
- [ ] Check Flex message displays timeline correctly

### 4. Test Preferences
- [ ] Open LIFF notification settings
- [ ] Toggle some events on/off
- [ ] Save preferences
- [ ] Send webhook for disabled event
- [ ] Verify notification skipped

## 📊 Monitoring Setup

### 1. Database Monitoring
- [ ] Set up query to monitor queue size:
  ```sql
  SELECT COUNT(*) FROM odoo_notification_queue WHERE status = 'pending';
  ```
- [ ] Alert if queue > 1000

### 2. Worker Monitoring
- [ ] Check worker logs: `tail -f logs/notification-worker.log`
- [ ] Verify worker processes notifications every 5 seconds
- [ ] Check for errors in logs

### 3. Success Rate Monitoring
- [ ] Query success rate:
  ```sql
  SELECT 
    status,
    COUNT(*) * 100.0 / SUM(COUNT(*)) OVER() as percentage
  FROM odoo_notification_log
  WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  GROUP BY status;
  ```
- [ ] Alert if success rate < 95%

## 🔧 Configuration

### 1. Adjust Settings (if needed)
- [ ] Batch window: Default 300s (5 minutes)
- [ ] Quiet hours: Default 22:00-08:00
- [ ] Worker poll interval: Default 5 seconds
- [ ] Queue batch size: Default 10 notifications
- [ ] Retry attempts: Default 3 times

### 2. Milestone Events
- [ ] Verify milestone: `order.packed`
- [ ] Confirm batch events: picker_assigned → picking → picked → packing → packed

### 3. LINE API
- [ ] Verify channel access token in `line_accounts` table
- [ ] Test LINE API connectivity
- [ ] Check quota limits

## 🎯 Go-Live

### 1. Enable for Test Users
- [ ] Select 5-10 test users
- [ ] Initialize their preferences
- [ ] Monitor their notifications for 24 hours

### 2. Gradual Rollout
- [ ] Day 1: 10% of users
- [ ] Day 2: 25% of users
- [ ] Day 3: 50% of users
- [ ] Day 4: 100% of users

### 3. Monitor Metrics
- [ ] Queue size
- [ ] Success rate
- [ ] Average latency
- [ ] Batch effectiveness
- [ ] User feedback

## 📈 Success Criteria

- ✅ Success rate > 95%
- ✅ Average latency < 2 seconds
- ✅ Queue size < 100
- ✅ No critical errors in logs
- ✅ Roadmap messages display correctly
- ✅ User preferences work
- ✅ Worker runs continuously

## 🐛 Rollback Plan

If issues occur:

1. **Stop worker**:
   ```bash
   pkill -f notification-worker
   ```

2. **Disable routing** (temporary):
   ```php
   // In OdooWebhookHandler.php, comment out:
   // $router = new NotificationRouter($this->db);
   // Use old sendNotifications() method
   ```

3. **Clear queue**:
   ```sql
   UPDATE odoo_notification_queue SET status = 'cancelled' WHERE status = 'pending';
   ```

4. **Investigate logs**:
   ```bash
   tail -100 logs/notification-worker.log
   ```

## 📞 Support Contacts

- **Database Issues**: Check `NOTIFICATION_SYSTEM_GUIDE.md`
- **Worker Issues**: Check `worker/README.md`
- **API Issues**: Check API logs
- **LINE API Issues**: Check LINE Developers Console

## ✅ Post-Deployment

### Week 1
- [ ] Monitor queue daily
- [ ] Review error logs
- [ ] Check success rate
- [ ] Collect user feedback

### Week 2
- [ ] Analyze batch effectiveness
- [ ] Optimize settings if needed
- [ ] Document any issues/solutions

### Month 1
- [ ] Review all metrics
- [ ] Plan improvements
- [ ] Update documentation

---

**Deployment Date**: _____________  
**Deployed By**: _____________  
**Status**: ⬜ Not Started | ⬜ In Progress | ⬜ Completed | ⬜ Rolled Back
