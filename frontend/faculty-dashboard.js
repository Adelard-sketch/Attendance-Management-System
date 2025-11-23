/**************************************
 * FACULTY DASHBOARD SCRIPT
 **************************************/

const API_BASE = 'http://localhost/Activity3/api';

document.addEventListener('DOMContentLoaded', () => {
  // Check authentication
  checkAuth();
  
  // Initialize page
  const user = getStoredUser();
  if (user) {
    document.getElementById('user-info').textContent = `Welcome, ${user.fullname || user.username} (${user.role})`;
  }
  
  // Load initial section
  loadSection('courses');
  
  // Event listeners
  document.querySelectorAll('nav a[data-section]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const section = link.dataset.section;
      
      document.querySelectorAll('nav a').forEach(l => l.classList.remove('active'));
      link.classList.add('active');
      
      loadSection(section);
    });
  });
  
  document.getElementById('createCourseBtn').addEventListener('click', (e) => {
    e.preventDefault();
    openModal('createCourseModal');
  });
  
  document.getElementById('createSessionBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    await loadCoursesForSession();
    openModal('createSessionModal');
  });
  
  document.getElementById('createCourseForm').addEventListener('submit', handleCreateCourse);
  document.getElementById('createSessionForm').addEventListener('submit', handleCreateSession);
  document.getElementById('saveAttendanceBtn').addEventListener('click', handleSaveAttendance);
  
  document.getElementById('logoutBtn').addEventListener('click', handleLogout);
  
  // Close modal handlers
  document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', () => {
      closeModal(closeBtn.dataset.modal);
    });
  });
  
  // Update stats
  updateStats();
});

function checkAuth() {
  const user = getStoredUser();
  if (!user || !['lecturer', 'intern'].includes(user.role)) {
    alert('Access denied. Faculty credentials required.');
    window.location.href = 'Login.html';
  }
}

function getStoredUser() {
  const username = localStorage.getItem('loggedInUser');
  const role = localStorage.getItem('userType');
  const fullname = localStorage.getItem('fullname');
  if (username && role) {
    return { username, role, fullname };
  }
  return null;
}

async function loadSection(section) {
  const container = document.getElementById('table-container');
  const contentArea = document.getElementById('dashboard-content');
  const heading = contentArea.querySelector('h2');
  
  container.innerHTML = '<p>Loading...</p>';
  
  try {
    switch(section) {
      case 'courses':
        heading.textContent = 'My Courses';
        await loadCourses();
        break;
      case 'requests':
        heading.textContent = 'Enrollment Requests';
        await loadRequests();
        break;
      case 'sessions':
        heading.textContent = 'Sessions & Attendance';
        await loadSessions();
        break;
      default:
        heading.textContent = capitalize(section);
        await loadGenericSection(section);
    }
  } catch (error) {
    container.innerHTML = `<p style="color:red;">Error loading ${section}: ${error.message}</p>`;
  }
}

async function loadCourses() {
  const response = await fetch(`${API_BASE}/manageCourses.php?action=list`, {
    credentials: 'include'
  });
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message || 'Failed to load courses');
  }
  
  const courses = data.courses || [];
  const container = document.getElementById('table-container');
  
  if (courses.length === 0) {
    container.innerHTML = '<p>No courses created yet. Click "Create New Course" to get started.</p>';
    return;
  }
  
  let html = `
    <table>
      <thead>
        <tr>
          <th>Course Code</th>
          <th>Course Name</th>
          <th>Credits</th>
          <th>Enrolled Students</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
  `;
  
  courses.forEach(course => {
    const enrolledCount = course.enrolled_students ? course.enrolled_students.length : 0;
    html += `
      <tr>
        <td>${course.course_code}</td>
        <td>${course.course_name}</td>
        <td>${course.credits || 3}</td>
        <td>${enrolledCount}</td>
        <td><span class="status-badge status-${course.status}">${course.status}</span></td>
        <td>
          <button class="btn btn-primary" onclick="viewCourse('${course._id}')">View</button>
          <button class="btn btn-danger" onclick="deleteCourse('${course._id}', '${course.course_code}')">Delete</button>
        </td>
      </tr>
    `;
  });
  
  html += '</tbody></table>';
  container.innerHTML = html;
}

async function loadRequests() {
  const response = await fetch(`${API_BASE}/manageEnrollments.php?action=pending`, {
    credentials: 'include'
  });
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message || 'Failed to load requests');
  }
  
  const requests = data.requests || [];
  const container = document.getElementById('table-container');
  
  if (requests.length === 0) {
    container.innerHTML = '<p>No pending enrollment requests.</p>';
    return;
  }
  
  let html = `
    <table>
      <thead>
        <tr>
          <th>Student Name</th>
          <th>Username</th>
          <th>Course Code</th>
          <th>Course Name</th>
          <th>Requested At</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
  `;
  
  requests.forEach(req => {
    const date = req.requested_at ? new Date(req.requested_at.$date || req.requested_at).toLocaleDateString() : 'N/A';
    html += `
      <tr>
        <td>${req.student_name}</td>
        <td>${req.student_username}</td>
        <td>${req.course_code}</td>
        <td>${req.course_name}</td>
        <td>${date}</td>
        <td><span class="status-badge status-${req.status}">${req.status}</span></td>
        <td>
          ${req.status === 'pending' ? `
            <button class="btn btn-success" onclick="handleRequest('${req._id}', 'approve')">Approve</button>
            <button class="btn btn-danger" onclick="handleRequest('${req._id}', 'reject')">Reject</button>
          ` : 'Reviewed'}
        </td>
      </tr>
    `;
  });
  
  html += '</tbody></table>';
  container.innerHTML = html;
}

async function loadGenericSection(section) {
  const response = await fetch(`${API_BASE}/dashboard.php?section=${section}`, {
    credentials: 'include'
  });
  const items = await response.json();
  
  const container = document.getElementById('table-container');
  
  if (!Array.isArray(items) || items.length === 0) {
    container.innerHTML = `<p>No ${section} data available.</p>`;
    return;
  }
  
  const headers = Object.keys(items[0]);
  let html = `
    <table>
      <thead>
        <tr>${headers.map(h => `<th>${capitalize(h)}</th>`).join('')}</tr>
      </thead>
      <tbody>
        ${items.map(item => `
          <tr>${headers.map(h => `<td>${item[h] ?? ''}</td>`).join('')}</tr>
        `).join('')}
      </tbody>
    </table>
  `;
  
  container.innerHTML = html;
}

async function handleCreateCourse(e) {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const data = {
    course_name: formData.get('course_name'),
    course_code: formData.get('course_code').toUpperCase(),
    description: formData.get('description'),
    credits: parseInt(formData.get('credits'))
  };
  
  try {
    const response = await fetch(`${API_BASE}/manageCourses.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert('✅ Course created successfully!');
      closeModal('createCourseModal');
      e.target.reset();
      loadSection('courses');
      updateStats();
    } else {
      alert('❌ ' + result.message);
    }
  } catch (error) {
    alert('❌ Error: ' + error.message);
  }
}

async function handleRequest(requestId, action) {
  if (!confirm(`Are you sure you want to ${action} this request?`)) {
    return;
  }
  
  try {
    const response = await fetch(`${API_BASE}/manageEnrollments.php`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ request_id: requestId, action })
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert(`✅ Request ${action}d successfully!`);
      loadSection('requests');
      updateStats();
    } else {
      alert('❌ ' + result.message);
    }
  } catch (error) {
    alert('❌ Error: ' + error.message);
  }
}

async function deleteCourse(courseId, courseCode) {
  if (!confirm(`Are you sure you want to delete course ${courseCode}? This cannot be undone.`)) {
    return;
  }
  
  try {
    const response = await fetch(`${API_BASE}/manageCourses.php`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ course_id: courseId })
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert('✅ Course deleted successfully!');
      loadSection('courses');
      updateStats();
    } else {
      alert('❌ ' + result.message);
    }
  } catch (error) {
    alert('❌ Error: ' + error.message);
  }
}

async function updateStats() {
  try {
    // Get courses count
    const coursesRes = await fetch(`${API_BASE}/manageCourses.php?action=list`, { credentials: 'include' });
    const coursesData = await coursesRes.json();
    const courses = coursesData.courses || [];
    document.getElementById('stat-courses').textContent = courses.length;
    
    // Calculate total students
    const totalStudents = courses.reduce((sum, course) => {
      return sum + (course.enrolled_students ? course.enrolled_students.length : 0);
    }, 0);
    document.getElementById('stat-students').textContent = totalStudents;
    
    // Get pending requests count
    const reqRes = await fetch(`${API_BASE}/manageEnrollments.php?action=pending`, { credentials: 'include' });
    const reqData = await reqRes.json();
    const requests = reqData.requests || [];
    document.getElementById('stat-requests').textContent = requests.length;
    
    // Get sessions count
    const sessionsRes = await fetch(`${API_BASE}/manageSessions.php?action=list`, { credentials: 'include' });
    const sessionsData = await sessionsRes.json();
    const sessions = sessionsData.sessions || [];
    document.getElementById('stat-sessions').textContent = sessions.length;
  } catch (error) {
    console.error('Error updating stats:', error);
  }
}

function viewCourse(courseId) {
  alert('Course details view - Coming soon!');
  // Implement course details view
}

async function handleLogout() {
  try {
    await fetch(`${API_BASE}/logout.php`, {
      method: 'POST',
      credentials: 'include'
    });
  } catch (error) {
    console.error('Logout error:', error);
  }
  
  localStorage.clear();
  window.location.href = 'Login.html';
}

function openModal(modalId) {
  document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = 'none';
}

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

// Close modal when clicking outside
window.onclick = function(event) {
  if (event.target.classList.contains('modal')) {
    event.target.style.display = 'none';
  }
}

/**************************************
 * SESSION MANAGEMENT FUNCTIONS
 **************************************/

async function loadSessions() {
  const response = await fetch(`${API_BASE}/manageSessions.php?action=list`, {
    credentials: 'include'
  });
  
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message);
  }
  
  const sessions = data.sessions || [];
  const container = document.getElementById('table-container');
  
  if (sessions.length === 0) {
    container.innerHTML = '<p>No sessions created yet. Click "Add New Session" to create one.</p>';
    return;
  }
  
  let html = `
    <table>
      <thead>
        <tr>
          <th>Course</th>
          <th>Session #</th>
          <th>Date</th>
          <th>Time</th>
          <th>Location</th>
          <th>Status</th>
          <th>Students</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
  `;
  
  sessions.forEach(session => {
    const statusClass = 
      session.status === 'completed' ? 'status-approved' :
      session.status === 'in-progress' ? 'status-pending' :
      session.status === 'cancelled' ? 'status-rejected' : '';
    
    html += `
      <tr>
        <td><strong>${session.course_code}</strong><br><small>${session.course_name}</small></td>
        <td>${session.session_number}</td>
        <td>${formatDate(session.date)}</td>
        <td>${session.start_time} - ${session.end_time}</td>
        <td>${session.location || 'N/A'}</td>
        <td><span class="status-badge ${statusClass}">${session.status}</span></td>
        <td>${session.total_students || 0}</td>
        <td>
          <button class="btn btn-primary" onclick="openAttendanceModal('${session._id}')">Mark Attendance</button>
          <button class="btn btn-secondary" onclick="viewSessionDetails('${session._id}')">View</button>
          ${session.status === 'scheduled' ? `<button class="btn btn-danger" onclick="deleteSession('${session._id}', '${session.course_code} #${session.session_number}')">Delete</button>` : ''}
        </td>
      </tr>
    `;
  });
  
  html += '</tbody></table>';
  container.innerHTML = html;
}

async function loadCoursesForSession() {
  const response = await fetch(`${API_BASE}/manageCourses.php?action=list`, {
    credentials: 'include'
  });
  
  const data = await response.json();
  
  if (data.success && data.courses) {
    const select = document.getElementById('session_course_id');
    select.innerHTML = '<option value="">Select a course...</option>';
    
    data.courses.forEach(course => {
      const option = document.createElement('option');
      option.value = course._id;
      option.textContent = `${course.course_code} - ${course.course_name}`;
      select.appendChild(option);
    });
  }
}

async function handleCreateSession(e) {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const data = {
    course_id: formData.get('course_id'),
    session_number: parseInt(formData.get('session_number')),
    date: formData.get('date'),
    start_time: formData.get('start_time'),
    end_time: formData.get('end_time'),
    location: formData.get('location'),
    description: formData.get('description'),
    status: 'scheduled'
  };
  
  try {
    const response = await fetch(`${API_BASE}/manageSessions.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert('✅ Session created successfully!');
      closeModal('createSessionModal');
      e.target.reset();
      loadSection('sessions');
      updateStats();
    } else {
      alert('❌ ' + result.message);
    }
  } catch (error) {
    alert('❌ Error: ' + error.message);
  }
}

async function deleteSession(sessionId, sessionName) {
  if (!confirm(`Are you sure you want to delete ${sessionName}? This cannot be undone.`)) {
    return;
  }
  
  try {
    const response = await fetch(`${API_BASE}/manageSessions.php`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ session_id: sessionId })
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert('✅ Session deleted successfully!');
      loadSection('sessions');
      updateStats();
    } else {
      alert('❌ ' + result.message);
    }
  } catch (error) {
    alert('❌ Error: ' + error.message);
  }
}

async function viewSessionDetails(sessionId) {
  try {
    const response = await fetch(`${API_BASE}/manageSessions.php?action=details&session_id=${sessionId}`, {
      credentials: 'include'
    });
    
    const data = await response.json();
    
    if (!data.success) {
      alert('❌ ' + data.message);
      return;
    }
    
    const session = data.session;
    const attendance = session.attendance || [];
    const enrolled = session.enrolled_students || [];
    
    const presentCount = attendance.filter(a => a.status === 'present').length;
    const absentCount = attendance.filter(a => a.status === 'absent').length;
    const lateCount = attendance.filter(a => a.status === 'late').length;
    
    let details = `
      <div style="padding: 20px;">
        <h3>${session.course_code} - Session ${session.session_number}</h3>
        <p><strong>Course:</strong> ${session.course_name}</p>
        <p><strong>Date:</strong> ${formatDate(session.date)}</p>
        <p><strong>Time:</strong> ${session.start_time} - ${session.end_time}</p>
        <p><strong>Location:</strong> ${session.location || 'N/A'}</p>
        <p><strong>Status:</strong> ${session.status}</p>
        ${session.description ? `<p><strong>Description:</strong> ${session.description}</p>` : ''}
        <hr>
        <h4>Attendance Summary</h4>
        <p>Total Enrolled: ${enrolled.length}</p>
        <p>Present: ${presentCount} | Absent: ${absentCount} | Late: ${lateCount}</p>
        <p>Attendance Rate: ${enrolled.length > 0 ? Math.round((presentCount + lateCount) / enrolled.length * 100) : 0}%</p>
      </div>
    `;
    
    const container = document.getElementById('table-container');
    container.innerHTML = details;
    
  } catch (error) {
    alert('❌ Error: ' + error.message);
  }
}

/**************************************
 * ATTENDANCE MANAGEMENT FUNCTIONS
 **************************************/

let currentSessionData = null;

async function openAttendanceModal(sessionId) {
  try {
    const response = await fetch(`${API_BASE}/manageSessions.php?action=details&session_id=${sessionId}`, {
      credentials: 'include'
    });
    
    const data = await response.json();
    
    if (!data.success) {
      alert('❌ ' + data.message);
      return;
    }
    
    currentSessionData = data.session;
    displayAttendanceForm();
    openModal('attendanceModal');
    
  } catch (error) {
    alert('❌ Error: ' + error.message);
  }
}

function displayAttendanceForm() {
  const session = currentSessionData;
  const enrolled = session.enrolled_students || [];
  const attendance = session.attendance || [];
  
  // Create attendance map for quick lookup
  const attendanceMap = {};
  attendance.forEach(record => {
    attendanceMap[record.student_id] = record.status;
  });
  
  // Display session info
  const infoDiv = document.getElementById('attendance-session-info');
  infoDiv.innerHTML = `
    <strong>${session.course_code} - Session ${session.session_number}</strong><br>
    ${formatDate(session.date)} | ${session.start_time} - ${session.end_time} | ${session.location || 'N/A'}
  `;
  
  // Display attendance table
  const container = document.getElementById('attendance-table-container');
  
  if (enrolled.length === 0) {
    container.innerHTML = '<p>No enrolled students found for this course.</p>';
    return;
  }
  
  let html = `
    <table>
      <thead>
        <tr>
          <th>Student Name</th>
          <th>Username</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
  `;
  
  enrolled.forEach(student => {
    const currentStatus = attendanceMap[student.student_id] || '';
    
    html += `
      <tr>
        <td>${student.student_name}</td>
        <td>${student.student_username}</td>
        <td>
          <select class="attendance-select" data-student-id="${student.student_id}" data-student-name="${student.student_name}" data-student-username="${student.student_username}">
            <option value="">Not Marked</option>
            <option value="present" ${currentStatus === 'present' ? 'selected' : ''}>Present</option>
            <option value="absent" ${currentStatus === 'absent' ? 'selected' : ''}>Absent</option>
            <option value="late" ${currentStatus === 'late' ? 'selected' : ''}>Late</option>
            <option value="excused" ${currentStatus === 'excused' ? 'selected' : ''}>Excused</option>
          </select>
        </td>
      </tr>
    `;
  });
  
  html += '</tbody></table>';
  container.innerHTML = html;
}

async function handleSaveAttendance() {
  const session = currentSessionData;
  
  if (!session) {
    alert('❌ No session data found');
    return;
  }
  
  // Collect attendance data
  const selects = document.querySelectorAll('.attendance-select');
  const attendanceData = [];
  
  selects.forEach(select => {
    const status = select.value;
    if (status) {
      attendanceData.push({
        student_id: select.dataset.studentId,
        student_name: select.dataset.studentName,
        student_username: select.dataset.studentUsername,
        status: status
      });
    }
  });
  
  if (attendanceData.length === 0) {
    if (!confirm('No attendance marked. Continue anyway?')) {
      return;
    }
  }
  
  try {
    const response = await fetch(`${API_BASE}/manageAttendance.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'mark',
        session_id: session._id,
        attendance: attendanceData
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert(`✅ Attendance saved successfully! (${result.marked_count} students marked)`);
      closeModal('attendanceModal');
      loadSection('sessions');
    } else {
      alert('❌ ' + result.message);
    }
  } catch (error) {
    alert('❌ Error: ' + error.message);
  }
}

function formatDate(dateStr) {
  const date = new Date(dateStr);
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  return date.toLocaleDateString('en-US', options);
}
