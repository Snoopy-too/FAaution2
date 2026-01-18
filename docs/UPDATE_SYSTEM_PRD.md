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

**Step 1: Backup (`action: backup`)**
- Create `backups/` directory if missing.
- source: Recursive scan of root directory.
- Exclusions: `.git`, `node_modules`, `backups`, `temp_update`, `Create_dist.php`.
- Action: Zip all files into `backups/backup_YYYY-MM-DD_HH-mm-ss.zip`.
- **Validation**: Check for `ZipArchive` availability.

**Step 2: Download (`action: download`)**
- Create `temp_update/` directory.
- Fetch `.zip` from GitHub `assets` or `zipball_url`.
- stream save to `temp_update/update.zip`.
- **Validation**: Verify HTTP 200 OK and non-zero file size.

**Step 3: Install (`action: install`)**
- Extract `temp_update/update.zip` to `temp_update/extracted/`.
- **Root Resolution**: Detect if files are nested in a subfolder (GitHub standard behavior `Repo-Tag/`) and map that as the source.
- **File Copy**: Recursive copy from source to Root.
    - **Overwrite**: `version.txt` (CRITICAL), code files.
    - **Skip/Protect**: `config/database.php` (User Config), `images/uploads/`, `team_logos/`, `person_pictures/` (User Data).
- **Validation**: Check `is_writable()` on destination files to prevent partial failure.

**Step 4: Migration (`action: migrate`)**
- Run `includes/MigrationRunner.php` to apply any pending SQL schema changes.
- **Cleanup**: Delete `temp_update/`.
- **Finalize**: Delete `cache/update_check.json` to reflect new version.

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
