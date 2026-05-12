# Profile Update Guide

## Changes Made

### 1. Database Schema Updates
New fields added to the `users` table:
- `student_id` (VARCHAR 50) - Unique student identifier
- `phone` (VARCHAR 20) - Phone number
- `address` (TEXT) - User address
- `profile_picture_path` (VARCHAR 255) - Path to profile picture

### 2. How to Apply the Migration

If you have an existing database, run this SQL migration:

```sql
ALTER TABLE users ADD COLUMN student_id VARCHAR(50) DEFAULT NULL UNIQUE;
ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL;
ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL;
ALTER TABLE users ADD COLUMN profile_picture_path VARCHAR(255) DEFAULT NULL;

CREATE INDEX idx_student_id ON users(student_id);
```

Or run the migration file:
```bash
mysql -u root -p green_rewards_sustainability_platform < database/migration_add_profile_fields.sql
```

### 3. Profile Features Updated

#### Edit Profile Form
- Changed "Edit Name" heading to "Edit Profile"
- Added fields:
  - Student ID
  - Phone Number
  - Address
  - Department (dropdown from departments table)

#### Profile Picture Upload
- New section to upload profile pictures
- Supported formats: JPEG, PNG, GIF, WebP
- Max file size: 5MB
- Pictures saved to `/uploads/profiles/` directory
- Old pictures automatically deleted when new one uploaded

#### Profile Display
- Profile picture shown in the top card (beside name and email)
- Falls back to avatar with initial if no picture uploaded
- Student ID displayed below email in top card

### 4. Directory Structure
Make sure this directory exists:
```
uploads/
└── profiles/
```

The application will create it automatically if it doesn't exist.

### 5. File Changes Summary
- `profile.php` - Updated with new form fields and profile picture upload
- `database/schema.sql` - Updated users table definition
- `database/migration_add_profile_fields.sql` - New migration file for existing databases
- `css/style.css` - Added styles for profile picture display

### 6. Testing
After applying changes:
1. Run the migration on your database
2. Access `/profile.php` (must be logged in)
3. Upload a profile picture
4. Fill in the new fields (Student ID, Phone, Address, Department)
5. Verify changes are saved and displayed correctly

### 7. Notes
- Profile pictures are stored with filename: `profile_{user_id}_{timestamp}.{ext}`
- Student ID field is unique across users
- Phone validation accepts numbers, dashes, spaces, and parentheses
- Address field can hold up to 500 characters
