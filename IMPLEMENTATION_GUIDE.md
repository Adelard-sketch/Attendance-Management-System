# Attendance Management System - Implementation Guide

## Overview
This system provides a complete user authentication and course management platform with role-based access control for students and faculty members.

## Features Implemented

### 1. User Authentication
- **Registration System** (`api/register.php`)
  - Secure password hashing using PHP's `password_hash()` with `PASSWORD_DEFAULT`
  - Server-side validation for all inputs
  - Checks for duplicate usernames and emails
  - Password requirements: minimum 8 characters with letters and numbers
  
- **Login System** (`api/Login.php`)
  - Session-based authentication
  - Password verification using `password_verify()`
  - Role-based login (Student, Lecturer, Faculty Intern)
  - Input sanitization and validation

- **Session Management** (`api/auth_middleware.php`)
  - PHP session management for persistent authentication
  - Middleware functions for protecting routes
  - Role-based access control
  - Automatic redirect to login for unauthorized access

### 2. Faculty Dashboard Features
**Location:** `frontend/FacultyDashboard.html` and `faculty-dashboard.js`

#### Course Management
- **Create New Courses** (`api/manageCourses.php`)
  - Add courses with code, name, description, and credits
  - Validation of course code format (e.g., CS101, MATH201)
  - Only faculty/intern can create courses
  - Duplicate course code prevention

- **View My Courses**
  - Display all courses created by the logged-in faculty member
  - Shows enrolled student count for each course
  - Course status tracking

- **Delete Courses**
  - Faculty can delete their own courses
  - Confirmation prompt before deletion

#### Enrollment Request Management
- **View Pending Requests** (`api/manageEnrollments.php`)
  - See all student requests for faculty's courses
  - Display student information and request date
  - Real-time status updates

- **Approve/Reject Requests**
  - One-click approval or rejection
  - Automatic updates to course enrollment list
  - Students are notified through status change

#### Dashboard Statistics
- Total courses count
- Pending requests count
- Total enrolled students across all courses

### 3. Student Dashboard Features
**Location:** `frontend/StudentDashboard.html` and `student-dashboard.js`

#### Course Browsing
- **View Available Courses**
  - Browse all courses offered by faculty
  - See course details (code, name, instructor, credits)
  - View enrolled student count
  - Filter out already enrolled courses

#### Enrollment Management
- **Request to Join Courses**
  - Submit enrollment requests for desired courses
  - Prevent duplicate requests
  - Real-time status tracking (Pending, Approved, Rejected)

- **View My Enrolled Courses**
  - Display all approved courses
  - Access course details and instructor information
  - Track enrollment date

- **View My Requests**
  - See all submitted enrollment requests
  - Track request status and review dates
  - Identify pending, approved, and rejected requests

#### Dashboard Statistics
- Enrolled courses count
- Pending requests count
- Available courses count

### 4. Security Features

#### Password Security
- All passwords are hashed using `password_hash()` before storage
- Uses `PASSWORD_DEFAULT` algorithm (currently bcrypt)
- No plain text passwords stored in database
- Password verification using `password_verify()`

#### Input Validation

**Server-Side (PHP):**
- Username: 3-20 alphanumeric characters and underscores
- Email: Valid email format validation
- Full name: Minimum 2 words
- Password: Minimum 8 characters with letters and numbers
- Course code: Format validation (e.g., CS101)
- Input sanitization using `trim()` and validation functions

**Client-Side (JavaScript & HTML5):**
- HTML5 form validation attributes (`required`, `minlength`, `maxlength`, `pattern`)
- JavaScript validation before form submission
- Email format validation using regex
- Password strength checking
- Username format validation
- Immediate user feedback on invalid inputs

#### Access Control
- **Session-based authentication**
  - PHP sessions track logged-in users
  - Session variables: user_id, username, role, fullname, email

- **Route Protection**
  - `requireAuth()` function blocks unauthenticated access
  - `requireRole()` function enforces role-based permissions
  - Automatic redirect to login page for unauthorized access
  - API endpoints return 401/403 status codes

- **Role-Based Permissions**
  - Students: Can only request enrollment and view courses
  - Faculty/Intern: Can create courses and manage enrollment requests
  - Each role has separate dashboard with appropriate features

## API Endpoints

### Authentication
- `POST /api/register.php` - Register new user
- `POST /api/Login.php` - User login
- `POST /api/logout.php` - User logout

### Course Management
- `GET /api/manageCourses.php?action=list` - List courses
- `GET /api/manageCourses.php?action=details&course_id={id}` - Get course details
- `POST /api/manageCourses.php` - Create new course (Faculty only)
- `PUT /api/manageCourses.php` - Update course (Faculty only)
- `DELETE /api/manageCourses.php` - Delete course (Faculty only)

### Enrollment Management
- `GET /api/manageEnrollments.php?action=list` - List user's requests
- `GET /api/manageEnrollments.php?action=pending` - List pending requests (Faculty)
- `GET /api/manageEnrollments.php?action=enrolled` - List enrolled courses (Students)
- `POST /api/manageEnrollments.php` - Submit enrollment request (Students)
- `PUT /api/manageEnrollments.php` - Approve/reject request (Faculty)

### Dashboard
- `GET /api/dashboard.php?section={section}` - Get dashboard data (Authenticated users)

## Database Collections

### users
```javascript
{
  _id: ObjectId,
  fullname: String,
  email: String,
  username: String,
  password: String (hashed),
  role: String (student|lecturer|intern),
  created_at: Date,
  status: String
}
```

### courses
```javascript
{
  _id: ObjectId,
  course_name: String,
  course_code: String,
  description: String,
  credits: Number,
  instructor_id: String,
  instructor_name: String,
  created_at: Date,
  status: String,
  enrolled_students: Array
}
```

### enrollment_requests
```javascript
{
  _id: ObjectId,
  student_id: String,
  student_name: String,
  student_username: String,
  course_id: String,
  course_name: String,
  course_code: String,
  instructor_id: String,
  instructor_name: String,
  status: String (pending|approved|rejected),
  requested_at: Date,
  reviewed_at: Date,
  reviewed_by: String
}
```

## Setup Instructions

### Prerequisites
- PHP 7.4 or higher
- MongoDB 4.0 or higher
- Web server (XAMPP, WAMP, or similar)
- MongoDB PHP Driver
- Composer (for dependencies)

### Installation Steps

1. **Install MongoDB PHP Driver**
   ```bash
   composer require mongodb/mongodb
   ```

2. **Configure MongoDB Connection**
   - Ensure MongoDB is running on `mongodb://localhost:27017`
   - Database name: `ashesi`

3. **Configure PHP Sessions**
   - Ensure PHP sessions are enabled in `php.ini`
   - Set appropriate session storage location

4. **File Structure**
   ```
   Activity3/
   ├── api/
   │   ├── auth_middleware.php    (Authentication middleware)
   │   ├── config.php             (Database configuration)
   │   ├── Login.php              (Login handler)
   │   ├── logout.php             (Logout handler)
   │   ├── register.php           (Registration handler)
   │   ├── manageCourses.php      (Course management API)
   │   ├── manageEnrollments.php  (Enrollment management API)
   │   ├── dashboard.php          (Dashboard data API)
   │   └── vendor/                (Composer dependencies)
   └── frontend/
       ├── Login.html             (Login page)
       ├── Register.html          (Registration page)
       ├── FacultyDashboard.html  (Faculty dashboard)
       ├── StudentDashboard.html  (Student dashboard)
       ├── faculty-dashboard.js   (Faculty JavaScript)
       ├── student-dashboard.js   (Student JavaScript)
       ├── script.js              (Login/Register JavaScript)
       └── *.css                  (Stylesheets)
   ```

5. **Access the Application**
   - Registration: `http://localhost/Activity3/frontend/Register.html`
   - Login: `http://localhost/Activity3/frontend/Login.html`

## User Flow

### For Students:
1. Register with student role
2. Login with credentials
3. Browse available courses
4. Request to join courses
5. Track request status
6. View enrolled courses after approval

### For Faculty:
1. Register with lecturer/intern role
2. Login with credentials
3. Create new courses
4. View enrollment requests
5. Approve or reject student requests
6. Manage course roster

## Security Best Practices Implemented

1. **Password Hashing**: All passwords stored with bcrypt hashing
2. **Input Validation**: Both client and server-side validation
3. **SQL Injection Prevention**: Using MongoDB parameterized queries
4. **XSS Prevention**: Input sanitization with `trim()` and validation
5. **Session Management**: Secure PHP session handling
6. **CSRF Protection**: Same-origin policy enforcement
7. **Role-Based Access**: Middleware enforces permissions
8. **Error Handling**: Proper error messages without exposing system details

## Testing

### Test Accounts (Create via Registration)

**Student Account:**
- Full Name: Adelard Borauzima
- Email: adel.borauzima@ashesi.edu.gh
- Username: Adel
- Role: Student
- Password: Student123

**Faculty Account:**
- Full Name: Dr. Jomes
- Email: jomes@ashesi.edu.gh
- Username: Jamensa
- Role: Lecturer
- Password: Faculty123

### Test Scenarios

1. **Registration**
   - Try registering with weak password (should fail)
   - Try duplicate username (should fail)
   - Register valid user (should succeed)

2. **Login**
   - Try wrong credentials (should fail)
   - Try mismatched role (should fail)
   - Login with correct credentials (should succeed and redirect to appropriate dashboard)

3. **Course Management (Faculty)**
   - Create course with invalid code format (should fail)
   - Create valid course (should succeed)
   - Try creating duplicate course code (should fail)
   - Delete own course (should succeed)

4. **Enrollment (Student)**
   - Request to join course (should succeed)
   - Try duplicate request (should fail)
   - View request status (should display correctly)

5. **Request Management (Faculty)**
   - View pending requests (should display)
   - Approve request (should update student's enrollment)
   - Reject request (should update status)

## Troubleshooting

### Common Issues

1. **Session not persisting**
   - Check if sessions are enabled in PHP
   - Verify session storage permissions
   - Ensure cookies are enabled in browser

2. **MongoDB connection errors**
   - Verify MongoDB is running
   - Check MongoDB PHP driver installation
   - Confirm connection string in code

3. **CORS errors**
   - Add appropriate CORS headers if needed
   - Check browser console for specific errors

4. **Validation errors**
   - Check browser console for client-side validation issues
   - Review server logs for PHP errors
   - Verify form field names match API expectations

## Future Enhancements

- Email verification for new registrations
- Password reset functionality
- Course capacity limits
- Waitlist management
- Attendance tracking features
- Grade management
- Course scheduling
- Multi-factor authentication
- Activity logging and audit trails

## Support

For issues or questions, please contact the development team or refer to the project documentation.
