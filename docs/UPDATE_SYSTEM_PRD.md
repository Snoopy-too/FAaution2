# Product Requirement Document: In-App Update System

## 1. Overview
The In-App Update System allows administrators of the FA Auction application to easily update their installation to the latest version directly from the Admin Dashboard, without needing FTP access or manual file handling. The system pulls releases securely from the official GitHub repository.

## 2. Objectives
- **Ease of Use**: One-click update process for non-technical users.
- **Reliability**: Ensure the system handles network failures, permissions issues, and backups automatically.
- **Safety**: Always create a full backup before attempting any file changes.
- **Visibility**: Clear notifications when updates are available and detailed progress/error feedback during the update.

## 3. Technical Architecture

### 3.1 Components
| Component | File Path | Responsibility |
|-----------|-----------|----------------|
| **Version Tracker** | `/version.txt` | Stores the current installed semver (e.g., `2.0.1`). |
| **Update Logic** | `includes/functions.php` | Contains `checkForUpdates()`, `getCurrentVersion()`, and caching logic. |
| **API Client** | `includes/UpdateChecker.php` | (Optional Class Wrapper) Handles GitHub API communication. |
| **Dashboard UI** | `admin/index.php` | Displays "Update Available" alert card if a new version is detected. |
| **Update Page** | `admin/update.php` | Dedicated page for the update wizard (Backup -> Download -> Install -> Migrate). |
| **Processor** | `admin/process_update.php` | Backend handler for AJAX requests. Executes the actual system commands. |
| **Reset Tool** | `reset_updates.php` | Diagnostic tool to clear cache and reset "dismissed" flags. |

### 3.2 External Dependencies
- **GitHub API**: Used to fetch release tags and download URLs (`https://api.github.com/repos/Snoopy-too/FAaution2/releases/latest`).
- **PHP ZipArchive**: Required for creating backups and extracting update packages.
- **cURL / allow_url_fopen**: Required for communicating with GitHub.

## 4. Detailed Workflow

### 4.1 Check for Updates
1.  **Trigger**: On admin dashboard load (cached) or manual "Check Again" button.
2.  **Process**:
    - Check `cache/update_check.json` validity (24h expiry).
    - If expired/missing, fetch `.../releases/latest` from GitHub.
    - Compare `tag_name` (parsed as semver) vs `version.txt`.
3.  **Result**:
    - If `Remote > Local`: Display "Update Available".
    - If `Remote <= Local`: Display "You are up to date".
    - If Error: Display error message (e.g., "Connection Failed").

### 4.2 System Update Process (The 4 Steps)
The update wizard in `admin/update.php` orchestrates these steps sequentially via AJAX to `process_update.php`.

**Step 1: Backup (`step: backup`)**
- Create `backups/` directory if missing.
- Source: Recursive scan of root directory.
- Exclusions: `.git`, `node_modules`, `backups`, `temp_update`, `.vscode`, `Create_dist.php`.
- Action: Zip all files into `backups/backup_YYYY-MM-DD_HH-mm-ss.zip`.
- **Validation**: Check for `ZipArchive` availability.

**Step 2: Download (`step: download`)**
- Create `temp_update/` directory if missing.
- Fetch `.zip` from GitHub `zipball_url` using cURL with streaming.
- Save to `temp_update/update.zip`.
- **Validation**: Check for cURL errors during download.

**Step 3: Install (`step: install`)**
- Extract `temp_update/update.zip` to `temp_update/extracted/`.
- **Root Resolution**: Detect if files are nested in a subfolder (GitHub standard behavior `Repo-Tag/`) and map that as the source.
- **File Copy**: Recursive copy from source to Root.
    - **Overwrite**: `version.txt` (CRITICAL), code files.
    - **Skip/Protect**: `config/database.php` (User Config), `images/uploads/`, `assets/uploads/`, `team_logos/`, `person_pictures/`, `install/` (User Data & Security).
- **Validation**: Check `is_writable()` on destination files to prevent partial failure.
- **Cleanup**: Delete `temp_update/` directory after successful file copy.

**Step 4: Migration (`step: migrate`)**
- Run `includes/MigrationRunner.php` to apply any pending SQL schema changes.
- **Finalize**: Delete `cache/update_check.json` to reflect new version and clear update notification.

## 5. Server Requirements
To deploy this feature successfully, the hosting environment MUST meet these criteria:
- **PHP Version**: 7.4+
- **Extensions**: `php-zip`, `php-curl`, `php-json`, `php-pdo`.
- **Permissions**:
    - Web Server User (e.g., `www-data`) must have **WRITE** access to:
        - Root directory `/` (to update code).
        - `/version.txt`
        - `/backups`
        - `/cache`
        - `/temp_update`

## 6. Known Issues & Troubleshooting / "Gotchas"
- **GitHub Rate Limits**: Unauthenticated API calls are limited to 60/hour per IP.
    - *Mitigation*: Cache results for 24h.
- **Silent Failures**: `file_get_contents` may fail silently if `allow_url_fopen` is off.
    - *Fix*: Use a robust cURL fallback function with explicit error checking.
- **Zip Nesting**: GitHub zips assume a top-level directory. Unzipping blindly into root puts files in a folder.
    - *Fix*: Logic in `process_update.php` must "find" the real root inside the extracted folder.
- **Dismissal Logic**: If a user dismisses an update, they might interpret "No update shown" as "System broken".
    - *Fix*: Ensure "Check Again" on the Update Page explicitly bypasses the ignored flag.
- **Permission Denied**: Shared hosts often lock the root folder.
    - *Fix*: Run `permission_test.php` diagnostic script to confirm capabilities before blaming the code.

## 7. Development Guidelines
- **Creating a Release**:
    1. Update code locally.
    2. Bump version in `version.txt`.
    3. Commit & Push.
    4. **Create Tag/Release on GitHub** matching the `version.txt`.
- **Developing the Updater**:
    - Use `test_update.php` for checking API responses.
    - Use `permission_test.php` for environment validation.

## 8. Testing & Verification

### 8.1 Browser Automation Testing
When performing automated testing using the browser tool:
- > [!IMPORTANT]
  > Always ensure that any auto-filled login fields are cleared before typing credentials to avoid concatenation errors.
- **Verification Path**: Login -> Admin Dashboard -> Settings or Update Page.

### 8.2 Test Server Credentials
- **Login URL**: [https://allsimbaseball.com/faauction2/auth/login.php](https://allsimbaseball.com/faauction2/auth/login.php)
- **Email**: `f.montoya@gmail.com`
- **Username**: `admin`
- **Password**: `ashgfnYYc34`

## 9. Security Considerations

### 9.1 Authentication & Authorization
- All update endpoints require admin authentication via `requireAdmin()`.
- Session-based authentication is enforced before any update operation.
- Non-admin users cannot access `admin/update.php` or `admin/process_update.php`.

### 9.2 File System Security
- **Protected Files**: The following paths are never overwritten during updates:
  - `config/database.php` - User database credentials
  - `images/uploads/` - User-uploaded images
  - `assets/uploads/` - User-uploaded assets
  - `team_logos/` - Custom team logo images
  - `person_pictures/` - Player profile images
  - `install/` - Installation scripts (excluded entirely)
  - `backups/` - Backup archives
  - `.git/` - Git repository data
- **Write Permissions**: Before overwriting, the system checks `is_writable()` and throws an exception if permission is denied.

### 9.3 Network Security
- All GitHub API calls use HTTPS.
- User-Agent header is set to identify the application (`FA-Auction-App` or `FA-Auction-Updater`).
- cURL `CURLOPT_FOLLOWLOCATION` is enabled to handle redirects securely.

### 9.4 Rate Limiting
- GitHub API unauthenticated requests are limited to 60/hour per IP.
- Results are cached for 24 hours to minimize API calls.

## 10. Rollback / Recovery

### 10.1 Automatic Backup
Before every update, a full backup is created at `backups/backup_YYYY-MM-DD_HH-mm-ss.zip`.

### 10.2 Manual Rollback Procedure
If an update fails or causes issues:

1. **Locate Backup**: Find the most recent backup in the `backups/` directory.
2. **Extract Backup**: Unzip the backup archive to a temporary location.
3. **Restore Files**: Copy files from the backup to the application root, excluding:
   - `config/database.php` (keep current if DB is intact)
   - Any user-uploaded content directories
4. **Restore Database** (if needed): Import from any database backup taken before the update.
5. **Clear Cache**: Delete `cache/update_check.json` to reset update status.

### 10.3 Partial Failure Recovery
If the update fails mid-process:
- **After Backup**: Safe to retry; backup is complete.
- **After Download**: Safe to retry; delete `temp_update/` folder first.
- **During Install**: Manual intervention may be required. Restore from backup.
- **During Migration**: Database may be partially migrated. Check `migrations` table.

## 11. Error Codes Reference

| Step | Error Message | Cause | Resolution |
|------|--------------|-------|------------|
| Any | `Invalid request method` | Non-POST request | Ensure AJAX uses POST |
| Backup | `PHP Zip extension is not enabled` | `php-zip` not installed | Enable in `php.ini` |
| Backup | `Cannot create backup zip` | Permission denied on `backups/` | Fix directory permissions |
| Download | `No download URL provided` | Missing URL in POST data | Check `checkForUpdates()` return |
| Download | cURL error messages | Network failure | Check server connectivity |
| Install | `Update file not found` | `temp_update/update.zip` missing | Re-run download step |
| Install | `Permission denied: Cannot create directory X` | Write permission denied | Fix directory permissions |
| Install | `Permission denied: Cannot overwrite file X` | Write permission denied | Fix file permissions |
| Install | `Failed to open update zip` | Corrupt or invalid zip | Re-download the update |
| Migrate | Migration SQL error | Invalid SQL in migration file | Check migration file syntax |
| Any | `Invalid update step` | Unknown step parameter | Valid steps: backup, download, install, migrate |

## 12. UI/UX Flow

### 12.1 Dashboard Notification
- On admin dashboard load, `checkForUpdates()` is called.
- If an update is available and not dismissed, an alert card displays:
  - Current version vs. latest version
  - Link to the Update Page
  - Option to dismiss this version

### 12.2 Update Page States
| State | Display |
|-------|---------|
| **Error** | Connection error message with "Try Again" button |
| **Up to Date** | Green checkmark, current version, "Check Again" button |
| **Update Available** | Version comparison, changelog, warnings, "Start Update" button |
| **In Progress** | Progress bar (0-100%), step labels, real-time log |
| **Complete** | Success message with confetti icon, "Return to Dashboard" link |
| **Failed** | Error message, "Try Again" button |

### 12.3 Progress Steps Display
| Step | Label | Progress % |
|------|-------|-----------|
| Backup | "Creating Backup..." | 20% |
| Download | "Downloading Update..." | 50% |
| Install | "Installing Files..." | 80% |
| Migrate | "Updating Database..." | 100% |

## 13. Migration System Details

### 13.1 Migration Files
- Location: `database/migrations/`
- Format: `*.sql` files executed in alphabetical order
- Naming convention recommended: `YYYY_MM_DD_HHMMSS_description.sql`

### 13.2 Migration Tracking
- Table: `migrations`
- Columns: `id`, `migration` (filename), `batch`, `run_at`
- Each migration runs once and is recorded to prevent re-execution.

### 13.3 Batch Processing
- Migrations run in a single batch per update.
- Batch number increments with each update.
- Used for potential future rollback tracking.

## 14. Future Improvements / Roadmap

### 14.1 Near-Term Enhancements
- [ ] **Authenticated GitHub API**: Add PAT support to increase rate limit from 60 to 5000 requests/hour.
- [ ] **Delta Updates**: Download only changed files instead of full release zip.
- [ ] **Pre-flight Checks**: Verify disk space and permissions before starting update.
- [ ] **Maintenance Mode**: Automatically enable maintenance mode during updates.

### 14.2 Long-Term Features
- [ ] **Rollback UI**: One-click rollback from the Admin Panel.
- [ ] **Update Scheduling**: Schedule updates for low-traffic periods.
- [ ] **Beta Channel**: Option to receive pre-release updates.
- [ ] **Changelog Modal**: Display full changelog before update confirmation.
- [ ] **Email Notifications**: Notify admins when updates are available.

## 15. Appendix

### 15.1 File Reference Quick List

| File | Purpose |
|------|---------|
| `version.txt` | Current version (semver) |
| `cache/update_check.json` | Cached update status (24h TTL) |
| `includes/functions.php` | `checkForUpdates()`, `getCurrentVersion()`, `clearUpdateCache()` |
| `includes/MigrationRunner.php` | SQL migration executor |
| `admin/index.php` | Dashboard with update notification |
| `admin/update.php` | Update wizard UI |
| `admin/process_update.php` | AJAX backend for update steps |
| `reset_updates.php` | Clear cache & dismissed flags |
| `test_update.php` | API diagnostic tool |
| `permission_test.php` | File permission diagnostic |

### 15.2 API Parameter Reference

**POST to `admin/process_update.php`**

| Parameter | Required | Values | Description |
|-----------|----------|--------|-------------|
| `step` | Yes | `backup`, `download`, `install`, `migrate` | Update step to execute |
| `url` | For download | URL string | GitHub zipball download URL |

**Response Format (JSON)**
```json
{
  "success": true|false,
  "message": "Status or error message"
}
```
