# Database Safety Guide for Work Knowledge Base

## ⚠️ IMPORTANT: Understanding Cascade Deletion

Your database uses **FOREIGN KEY CASCADE DELETE** relationships. This means:

### When you delete a CATEGORY:
- ❌ All subcategories in that category are permanently deleted
- ❌ All posts in those subcategories are permanently deleted
- ❌ All replies to those posts are permanently deleted
- ❌ All files attached to posts/replies are permanently deleted

### When you delete a SUBCATEGORY:
- ❌ All posts in that subcategory are permanently deleted
- ❌ All replies to those posts are permanently deleted
- ❌ All files attached to posts/replies are permanently deleted

## 🛡️ Safe Management Practices

### 1. BEFORE Deleting Categories:
- ✅ Check if you have a backup
- ✅ Review what content will be lost
- ✅ Consider if you just want to rename instead

### 2. Use Soft Deletes Instead:
Instead of hard deletion, consider:
- Renaming categories to "Archived - [Old Name]"
- Moving content to a different category
- Adding a "status" field to mark as inactive

### 3. Backup Before Major Changes:
```sql
-- Create backups before deletion
CREATE TABLE categories_backup AS SELECT * FROM categories;
CREATE TABLE subcategories_backup AS SELECT * FROM subcategories;
CREATE TABLE posts_backup AS SELECT * FROM posts;
```

### 4. Alternative: Deactivate Instead of Delete
Add this to your categories table:
```sql
ALTER TABLE categories ADD COLUMN is_active BOOLEAN DEFAULT TRUE;
```

Then modify your queries to filter by `is_active = TRUE` instead of deleting.

## 📊 Current Database State (What You Have Left)

Based on your screenshot:
- **3 Categories** total
- **6 Subcategories** total (2 per category)
- **7 Posts** total across all categories

## 🚨 Recovery Options

### Option 1: Check Backups
- Do you have a recent database backup?
- Check if your hosting provider has automatic backups

### Option 2: Manual Recreation
- You may need to manually recreate the deleted categories
- Copy/paste content from any exported files if available

### Option 3: Contact Your Hosting Provider
- Ask about point-in-time recovery
- Check if they have daily/weekly backups

## 🔧 Modified Delete Scripts (Safer Versions)

### Safer Category Deletion (Shows What Will Be Deleted)
```php
// In delete_category.php - show confirmation with counts
$stmt = $pdo->prepare("
    SELECT
        c.name as category_name,
        COUNT(DISTINCT s.id) as subcategory_count,
        COUNT(DISTINCT p.id) as post_count,
        COUNT(DISTINCT r.id) as reply_count
    FROM categories c
    LEFT JOIN subcategories s ON c.id = s.category_id
    LEFT JOIN posts p ON s.id = p.subcategory_id
    LEFT JOIN replies r ON p.id = r.post_id
    WHERE c.id = ?
    GROUP BY c.id
");
```

### Soft Delete Implementation
```php
// Instead of DELETE, use UPDATE
$stmt = $pdo->prepare("UPDATE categories SET is_active = 0 WHERE id = ?");
```

## 📞 Help Resources

### If You Need Recovery:
1. Check hosting control panel for backups
2. Contact your hosting provider's support
3. Check if you exported any content as PDF before deletion

### For Future Safety:
1. Set up regular automated backups
2. Use soft deletes instead of hard deletes
3. Always preview what will be deleted before confirming

---

**REMEMBER:** The current system is designed for permanent deletion. Be very careful with the "Delete" buttons!