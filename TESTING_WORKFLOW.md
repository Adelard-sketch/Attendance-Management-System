# Attendance System - Testing Workflow

## Complete Self-Attendance Workflow Test

### Prerequisites
- XAMPP running with Apache and MongoDB
- At least one faculty/instructor account
- At least one student account
- Student must be enrolled in at least one course

### Step 1: Faculty Creates a Session
1. Login as Faculty/Instructor at `http://localhost/Activity3/frontend/Login.html`
2. Navigate to **Sessions & Attendance** tab
3. Click **Add New Session** button
4. Fill in session details:
   - Select a course
   - Enter session number
   - Set date to today
   - Set start time and end time
   - Add location
   - Add optional description
5. Click **Create Session**
6. **Expected Result**: Session created successfully with 6-digit attendance code generated

### Step 2: Faculty Starts the Session
1. In the Sessions table, locate the newly created session
2. **Observe**: Status should be "scheduled" and attendance code should be visible (e.g., "A3F5D8")
3. Click **Start Session** button
4. Confirm the action
5. **Expected Result**: 
   - Status changes to "in-progress"
   - Alert confirms "Session started! Share the attendance code with students."
   - Code remains visible and highlighted in green

### Step 3: Student Views Active Sessions
1. Login as Student at `http://localhost/Activity3/frontend/Login.html`
2. Navigate to **Mark Attendance** section
3. **Expected Result**:
   - Input field for 6-digit code is displayed
   - Today's active sessions list appears below
   - Sessions show: Course, Session #, Time, Location, Status badge

### Step 4: Student Marks Attendance
1. Enter the 6-digit attendance code (from Step 2) in the input field
2. Click **Submit Attendance** button
3. **Expected Result**:
   - Success message displays: "✅ Attendance marked successfully!"
   - Message shows: Course, Session #, Status (Present/Late)
   - Status is "Present" if within 15 minutes of start time
   - Status is "Late" if after 15 minutes
   - Session list automatically refreshes
   - Marked session now shows "✅ Marked" badge

### Step 5: Verify Duplicate Prevention
1. Try entering the same code again
2. Click **Submit Attendance**
3. **Expected Result**: Error message "❌ You have already marked attendance for this session"

### Step 6: Faculty Views Attendance
1. Return to Faculty dashboard
2. Navigate to **Sessions & Attendance** tab
3. Click **View** button for the session
4. **Expected Result**:
   - Attendance Summary shows updated counts
   - Present count includes the student who marked attendance
   - Attendance rate percentage updated

### Step 7: Faculty Ends Session
1. In Sessions table, click **End Session** button
2. Confirm the action
3. **Expected Result**:
   - Status changes to "completed"
   - Alert confirms "Session ended successfully!"
   - Start/End buttons no longer appear

### Step 8: Test Code Expiration
1. Create another session with today's date
2. Start the session
3. As faculty, end the session (status: completed)
4. As student, try to use the attendance code
5. **Expected Result**: Error message about invalid or expired code

## Edge Cases to Test

### Invalid Code Format
- **Test**: Enter less than 6 characters (e.g., "ABC")
- **Expected**: Alert "Please enter a 6-digit code"

### Wrong Code
- **Test**: Enter random 6-character code (e.g., "XXXXXX")
- **Expected**: Error "Invalid or expired attendance code"

### Not Enrolled Student
- **Test**: Student tries to mark attendance for course they're not enrolled in
- **Expected**: Error message about enrollment requirement

### Past Session Date
- **Test**: Try marking attendance for session scheduled on different day
- **Expected**: Error about session date mismatch

### Session Not Started
- **Test**: Try using code from scheduled session (not in-progress)
- **Expected**: Error about session not being active

## Faculty Features to Test

### Manual Attendance Marking
1. Click **Mark Attendance** button
2. Select statuses from dropdowns
3. Click **Save Attendance**
4. **Expected**: Attendance saved alongside student self-marked records

### View Session Details
1. Click **View** button
2. **Expected**: 
   - Full session details displayed
   - Attendance code shown prominently
   - Summary statistics accurate
   - Start/End session controls available based on status

### Delete Session
1. For scheduled session, click **Delete**
2. **Expected**: Session removed from database

## Known Behaviors

- **Present Status**: Marked if attendance submitted within 15 minutes of start_time
- **Late Status**: Marked if attendance submitted after 15-minute grace period
- **Code Format**: Always 6 uppercase alphanumeric characters
- **Session States**: scheduled → in-progress → completed
- **Code Visibility**: Always visible to faculty, used by students for marking

## Troubleshooting

### "Failed to fetch" errors
- Ensure Apache is running (port 80)
- Use `http://localhost` not `127.0.0.1:5500`
- Check MongoDB is running

### Code not displaying
- Check browser console for errors
- Verify manageSessions.php returns attendance_code field
- Ensure session was created after code generation was implemented

### Student can't see sessions
- Verify student is enrolled in the course
- Check session date is set to today
- Confirm session status is 'in-progress' or 'scheduled'

### Time-based status issues
- Server time should match actual time
- Check PHP date/time configuration
- Verify session start_time format is correct (HH:MM)
