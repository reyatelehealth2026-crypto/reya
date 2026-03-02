# Custom Display Name Fix - สรุปการแก้ไข

## ปัญหา
ชื่อที่แอดมินตั้งให้ลูกค้าใน inbox-v2.php มักจะ **rollback กลับเป็นชื่อเดิมจาก LINE** ทุกครั้งที่ลูกค้าส่งข้อความหรือมี webhook event เข้ามา

## สาเหตุ
ใน `webhook.php` function `getOrCreateUser()` มีการดึง profile จาก LINE API และ **overwrite `display_name`** ทุกครั้ง:

```php
// ❌ โค้ดเดิม - overwrite ทุกครั้ง
if (!empty($displayName) && $displayName !== 'Unknown' && $displayName !== $user['display_name']) {
    $updateFields[] = "display_name = ?";
    $updateValues[] = $displayName;
    $needsUpdate = true;
}
```

## วิธีแก้ไข

### 1. เพิ่มฟิลด์ `custom_display_name` ในตาราง `users`

```sql
ALTER TABLE users 
ADD COLUMN custom_display_name VARCHAR(255) NULL 
COMMENT 'Custom name set by admin (overrides LINE display_name)' 
AFTER display_name;
```

**ไฟล์ที่สร้าง:**
- `database/migration_custom_display_name.sql` - SQL migration
- `install/run_custom_display_name_migration.php` - Migration runner

### 2. แก้ไข API endpoint ให้บันทึกไปที่ `custom_display_name`

**ไฟล์:** `api/inbox-v2.php`

```php
// ✅ โค้ดใหม่ - บันทึกไปที่ custom_display_name
if ($field === 'display_name') {
    $stmt = $db->prepare("UPDATE users SET custom_display_name = ? WHERE id = ?");
} else {
    $stmt = $db->prepare("UPDATE users SET {$field} = ? WHERE id = ?");
}
```

### 3. แก้ไข webhook ให้ไม่ overwrite ถ้ามี `custom_display_name`

**ไฟล์:** `webhook.php`

```php
// ✅ โค้ดใหม่ - ไม่ overwrite ถ้ามี custom_display_name
if (empty($user['custom_display_name']) && !empty($displayName) && $displayName !== 'Unknown' && $displayName !== $user['display_name']) {
    $updateFields[] = "display_name = ?";
    $updateValues[] = $displayName;
    $needsUpdate = true;
}
```

### 4. แก้ไขการแสดงผลให้ใช้ `custom_display_name` ก่อน

**ไฟล์:** `inbox-v2.php`

```php
// ✅ ใช้ custom_display_name ถ้ามี ไม่งั้นใช้ display_name
$selectedUser['effective_display_name'] = $selectedUser['custom_display_name'] ?: $selectedUser['display_name'];
```

**ไฟล์:** `classes/InboxService.php`

```sql
-- ✅ ใช้ COALESCE ใน SQL query
SELECT
    u.id,
    COALESCE(u.custom_display_name, u.display_name) as display_name,
    ...
FROM users u
```

## วิธีติดตั้ง

### ขั้นตอนที่ 1: รัน Migration

```bash
cd re-ya
php install/run_custom_display_name_migration.php
```

### ขั้นตอนที่ 2: ทดสอบการทำงาน

1. เปิด inbox-v2.php
2. เลือกลูกค้าคนหนึ่ง
3. คลิกปุ่ม ✏️ แก้ไขชื่อ
4. ตั้งชื่อใหม่ เช่น "คุณลูกค้า VIP"
5. ให้ลูกค้าส่งข้อความเข้ามา
6. ✅ ชื่อควรยังคงเป็น "คุณลูกค้า VIP" ไม่เปลี่ยนกลับ

## โครงสร้างข้อมูล

### ตาราง `users`

| Field | Type | Description |
|-------|------|-------------|
| `display_name` | VARCHAR(255) | ชื่อจาก LINE API (อัปเดตอัตโนมัติ) |
| `custom_display_name` | VARCHAR(255) | ชื่อที่แอดมินตั้งเอง (ไม่ถูก overwrite) |

### ลำดับความสำคัญในการแสดงผล

1. **`custom_display_name`** - ถ้ามีค่า ใช้อันนี้ก่อน
2. **`display_name`** - ถ้าไม่มี custom_display_name ใช้อันนี้

## ไฟล์ที่แก้ไข

### ไฟล์ใหม่
- ✅ `database/migration_custom_display_name.sql`
- ✅ `install/run_custom_display_name_migration.php`
- ✅ `CUSTOM_DISPLAY_NAME_FIX_SUMMARY.md` (ไฟล์นี้)

### ไฟล์ที่แก้ไข
- ✅ `webhook.php` - แก้ getOrCreateUser() ไม่ให้ overwrite
- ✅ `api/inbox-v2.php` - แก้ update_customer_info ให้บันทึกไปที่ custom_display_name
- ✅ `inbox-v2.php` - แก้การแสดงผลให้ใช้ effective_display_name
- ✅ `classes/InboxService.php` - แก้ SQL query ให้ใช้ COALESCE

## ข้อดีของวิธีนี้

1. ✅ **ไม่ทำลายข้อมูลเดิม** - `display_name` จาก LINE ยังอัปเดตปกติ
2. ✅ **แยกข้อมูลชัดเจน** - แยกระหว่างชื่อจาก LINE กับชื่อที่แอดมินตั้ง
3. ✅ **Backward compatible** - ถ้าไม่มี custom_display_name จะใช้ display_name
4. ✅ **ไม่กระทบระบบเดิม** - ระบบอื่นที่ใช้ display_name ยังทำงานได้ปกติ

## การทดสอบเพิ่มเติม

### Test Case 1: ตั้งชื่อใหม่
- [ ] แอดมินตั้งชื่อใหม่ให้ลูกค้า
- [ ] ลูกค้าส่งข้อความ
- [ ] ชื่อไม่เปลี่ยนกลับ ✅

### Test Case 2: ลบชื่อที่ตั้ง
- [ ] แอดมินลบชื่อที่ตั้งไว้ (ตั้งเป็นค่าว่าง)
- [ ] ระบบควรกลับไปใช้ชื่อจาก LINE

### Test Case 3: ค้นหา
- [ ] ค้นหาด้วยชื่อที่แอดมินตั้ง
- [ ] ควรหาเจอ ✅

### Test Case 4: Conversation List
- [ ] รายการ conversations แสดงชื่อที่แอดมินตั้ง
- [ ] ไม่ใช่ชื่อจาก LINE ✅

## หมายเหตุ

- ฟิลด์ `custom_display_name` เป็น **NULL** ได้ (ถ้าแอดมินไม่ได้ตั้งชื่อ)
- ถ้า `custom_display_name` เป็น NULL หรือค่าว่าง ระบบจะใช้ `display_name` จาก LINE
- การอัปเดต `display_name` จาก LINE API ยังทำงานปกติ แต่จะไม่ overwrite `custom_display_name`

## สรุป

การแก้ไขนี้แก้ปัญหา **ชื่อที่แอดมินตั้งให้ลูกค้า rollback กลับเป็นชื่อเดิม** โดยเพิ่มฟิลด์ `custom_display_name` เพื่อเก็บชื่อที่แอดมินตั้งแยกจากชื่อที่มาจาก LINE API ทำให้แอดมินสามารถตั้งชื่อเล่นหรือชื่อที่จดจำง่ายให้ลูกค้าได้โดยไม่ถูก overwrite
