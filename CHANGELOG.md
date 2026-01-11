# FA Auction - Changelog

## Version 2.0.0 (Current)

### Archive System
- Archive free agent classes with full bid history
- Browse archived seasons with tabbed interface
- Budget isolation: archived contracts don't count against current cap
- Protected deletion: "Clear All" only affects active players

### Team Logos
- Display team logos throughout application
- Configurable logo path in settings
- Automatic fallback to team name if logo missing

### UI Improvements
- Dynamic "Winning Bid" vs "Current Highest Bid" based on auction status
- Custom styled confirmation modals
- Improved mobile responsiveness

### Database
- New `archives` table for season archiving
- `archive_id` column in `players` table
- Migration tracking system

---

## Version 1.0.0
- Initial release
- Player management and CSV import
- Bidding system with real-time validation
- Team budget tracking
- Admin and member dashboards
