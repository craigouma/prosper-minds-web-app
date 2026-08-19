# ProsperMinds CPD Event Management System

A simple yet powerful CPD event management system built with pure PHP, MySQL, HTML, and CSS.

## Features

- **Modern UI Design**: Clean, responsive layout with rounded cards, gradients, and #02AD2F green theme
- **Event Listing**: Display all 12 CPD events with full details
- **Registration System**: Modal popup form for easy registration
- **Database Storage**: All registrations saved in MySQL
- **Email Notifications**: Automatic emails to both user and admin
- **Responsive Design**: Works on mobile, tablet, and desktop

## File Structure

```
prosperminds-cpd/
├── config.php                # Database & email configuration
├── setup_database.sql        # SQL for creating tables and sample data
├── index.php                 # Main landing page with event cards
├── register.php              # Form processing and email sending
├── success.php               # Registration confirmation page
├── styles.css                # Modern CSS styling
├── scripts.js                # Modal functionality
└── README.md                 # This file
```

## Setup Instructions

### 1. Database Setup
1. Import `setup_database.sql` into your MySQL database
2. Update `config.php` with your database credentials if different from default

### 2. Email Configuration
- The system uses PHP's `mail()` function
- Update email settings in `config.php` if needed

### 3. Running the System
1. Place all files in your web server directory
2. Access `index.php` through your browser
3. The system will automatically display all events from the database

## Technical Details

### Database Tables
- **events**: Stores all event information (title, dates, venue, etc.)
- **registrations**: Stores user registration data linked to events

### Security Features
- Input sanitization and validation
- Prepared statements to prevent SQL injection
- Email format validation
- CSRF protection (can be added)

### UI Features
- Modern card-based layout
- Gradient backgrounds using #02AD2F
- Smooth animations and transitions
- Responsive grid layout
- Accessible form design

## Customization

### Colors
Edit the CSS variables in `styles.css`:
```css
:root {
    --primary-color: #02AD2F;  /* Change this to your preferred color */
    --primary-gradient: linear-gradient(135deg, #02AD2F, #088A29);
}
```

### Events
Add or modify events by:
1. Updating the database directly
2. Or modifying the SQL insert statements in `setup_database.sql`

## Requirements

- PHP 7.0+
- MySQL 5.6+
- Web server (Apache, Nginx, etc.)
- PHP mail() function enabled or SMTP configured

## License

This system is provided as-is for ProsperMinds CPD event management.
