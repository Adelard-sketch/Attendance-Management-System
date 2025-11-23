
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
  loadSection('enrolled');
  
  // Event listeners for nav and sidebar links
  document.querySelectorAll('nav a[data-section], aside a[data-section]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const section = link.dataset.section;
      
      document.querySelectorAll('nav a').forEach(l => l.classList.remove('active'));
      document.querySelector(`nav a[data-section="${section}"]`)?.classList.add('active');
      
      loadSection(section);
    });
  });
  
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
  if (!user || user.role !== 'student') {
    alert('Access denied. Student credentials required.');
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
      case 'enrolled':
        heading.textContent = 'My Enrolled Courses';
        await loadEnrolledCourses();
        break;
      case 'available':
        heading.textContent = 'Available Courses';
        await loadAvailableCourses();
        break;
      case 'requests':
        heading.textContent = 'My Enrollment Requests';
        await loadMyRequests();
        break;
      case 'attendance':
        heading.textContent = 'My Attendance Records';
        await loadMyAttendance();
        break;
      default:
        heading.textContent = capitalize(section);
        container.innerHTML = '<p>Section not available.</p>';
    }
  } catch (error) {
    container.innerHTML = `<p style="color:red;">Error loading ${section}: ${error.message}</p>`;
  }
}

async function loadEnrolledCourses() {
  const response = await fetch(`${API_BASE}/manageEnrollments.php?action=enrolled`, {
    credentials: 'include'
  });
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message || 'Failed to load enrolled courses');
  }
  
  const courses = data.courses || [];
  const container = document.getElementById('table-container');
  
  if (courses.length === 0) {
    container.innerHTML = '<p>You are not enrolled in any courses yet. <a href="#" data-section="available">Browse available courses</a> to get started.</p>';
    // Re-attach event listener
    container.querySelector('a').addEventListener('click', (e) => {
      e.preventDefault();
      loadSection('available');
    });
    return;
  }
  
  let html = '<div class="course-grid">';
  
  courses.forEach(course => {
    const enrolledCount = course.enrolled_students ? course.enrolled_students.length : 0;
    html += `
      <div class="course-card">
        <h3>${course.course_code}: ${course.course_name}</h3>
        <p><strong>Instructor:</strong> ${course.instructor_name}</p>
        <p><strong>Credits:</strong> ${course.credits || 3}</p>
        <p><strong>Enrolled Students:</strong> ${enrolledCount}</p>
        ${course.description ? `<p>${course.description}</p>` : ''}
        <button class="btn btn-primary" onclick="viewCourseDetails('${course._id}')">View Details</button>
      </div>
    `;
  });
  
  html += '</div>';
  container.innerHTML = html;
}

async function loadAvailableCourses() {
  const response = await fetch(`${API_BASE}/manageCourses.php?action=list`, {
    credentials: 'include'
  });
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message || 'Failed to load courses');
  }
  
  const allCourses = data.courses || [];
  
  // Get enrolled courses to filter them out
  const enrolledRes = await fetch(`${API_BASE}/manageEnrollments.php?action=enrolled`, {
    credentials: 'include'
  });
  const enrolledData = await enrolledRes.json();
  const enrolledIds = enrolledData.success ? (enrolledData.courses || []).map(c => c._id) : [];
  
  // Get pending requests
  const requestsRes = await fetch(`${API_BASE}/manageEnrollments.php?action=list`, {
    credentials: 'include'
  });
  const requestsData = await requestsRes.json();
  const pendingCourseIds = requestsData.success ? 
    (requestsData.requests || [])
      .filter(r => r.status === 'pending')
      .map(r => r.course_id) : [];
  
  const container = document.getElementById('table-container');
  
  if (allCourses.length === 0) {
    container.innerHTML = '<p>No courses available at the moment.</p>';
    return;
  }
  
  let html = '<div class="course-grid">';
  
  allCourses.forEach(course => {
    const isEnrolled = enrolledIds.includes(course._id);
    const isPending = pendingCourseIds.includes(course._id);
    const enrolledCount = course.enrolled_students ? course.enrolled_students.length : 0;
    
    html += `
      <div class="course-card">
        <h3>${course.course_code}: ${course.course_name}</h3>
        <p><strong>Instructor:</strong> ${course.instructor_name}</p>
        <p><strong>Credits:</strong> ${course.credits || 3}</p>
        <p><strong>Enrolled Students:</strong> ${enrolledCount}</p>
        ${course.description ? `<p>${course.description}</p>` : ''}
        <div>
          ${isEnrolled ? 
            '<button class="btn btn-success" disabled>Already Enrolled</button>' :
            isPending ?
              '<button class="btn btn-warning" disabled>Request Pending</button>' :
              `<button class="btn btn-primary" onclick="requestEnrollment('${course._id}', '${course.course_code}')">Request to Join</button>`
          }
        </div>
      </div>
    `;
  });
  
  html += '</div>';
  container.innerHTML = html;
}

async function loadMyRequests() {
  const response = await fetch(`${API_BASE}/manageEnrollments.php?action=list`, {
    credentials: 'include'
  });
  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message || 'Failed to load requests');
  }
  
  const requests = data.requests || [];
  const container = document.getElementById('table-container');
  
  if (requests.length === 0) {
    container.innerHTML = '<p>You have not made any enrollment requests yet.</p>';
    return;
  }
  
  let html = `
    <table>
      <thead>
        <tr>
          <th>Course Code</th>
          <th>Course Name</th>
          <th>Instructor</th>
          <th>Requested At</th>
          <th>Status</th>
          <th>Reviewed At</th>
        </tr>
      </thead>
      <tbody>
  `;
  
  requests.forEach(req => {
    const requestedDate = req.requested_at ? new Date(req.requested_at.$date || req.requested_at).toLocaleDateString() : 'N/A';
    const reviewedDate = req.reviewed_at ? new Date(req.reviewed_at.$date || req.reviewed_at).toLocaleDateString() : '-';
    
    html += `
      <tr>
        <td>${req.course_code}</td>
        <td>${req.course_name}</td>
        <td>${req.instructor_name}</td>
        <td>${requestedDate}</td>
        <td><span class="status-badge status-${req.status}">${req.status}</span></td>
        <td>${reviewedDate}</td>
      </tr>
    `;
  });
  
  html += '</tbody></table>';
  container.innerHTML = html;
}

async function requestEnrollment(courseId, courseCode) {
  if (!confirm(`Request to join course ${courseCode}?`)) {
    return;
  }
  
  try {
    const response = await fetch(`${API_BASE}/manageEnrollments.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ course_id: courseId })
    });
    
    const result = await response.json();
    
    if (result.success) {
      alert('✅ Enrollment request submitted successfully! Please wait for faculty approval.');
      loadSection('available');
      updateStats();
    } else {
      alert('❌ ' + result.message);
    }
  } catch (error) {
    alert('❌ Error: ' + error.message);
  }
}

function viewCourseDetails(courseId) {
  alert('Course details view - Coming soon!');
  // Implement detailed course view
}

async function updateStats() {
  try {
    // Get enrolled courses count
    const enrolledRes = await fetch(`${API_BASE}/manageEnrollments.php?action=enrolled`, { credentials: 'include' });
    const enrolledData = await enrolledRes.json();
    const enrolledCount = enrolledData.success ? (enrolledData.courses || []).length : 0;
    document.getElementById('stat-enrolled').textContent = enrolledCount;
    
    // Get pending requests count
    const reqRes = await fetch(`${API_BASE}/manageEnrollments.php?action=list`, { credentials: 'include' });
    const reqData = await reqRes.json();
    const requests = reqData.success ? (reqData.requests || []) : [];
    const pendingCount = requests.filter(r => r.status === 'pending').length;
    document.getElementById('stat-pending').textContent = pendingCount;
    
    // Get available courses count
    const coursesRes = await fetch(`${API_BASE}/manageCourses.php?action=list`, { credentials: 'include' });
    const coursesData = await coursesRes.json();
    const availableCount = coursesData.success ? (coursesData.courses || []).length : 0;
    document.getElementById('stat-available').textContent = availableCount;
  } catch (error) {
    console.error('Error updating stats:', error);
  }
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
 * ATTENDANCE VIEWING FUNCTIONS
 **************************************/

async function loadMyAttendance() {
  try {
    const response = await fetch(`${API_BASE}/manageAttendance.php?action=student_attendance`, {
      credentials: 'include'
    });
    
    const data = await response.json();
    
    if (!data.success) {
      throw new Error(data.message);
    }
    
    const attendance = data.attendance || [];
    const container = document.getElementById('table-container');
    
    if (attendance.length === 0) {
      container.innerHTML = '<p>No attendance records found. Attendance will appear here after instructors mark attendance for your courses.</p>';
      return;
    }
    
    // Group by course
    const byCourse = {};
    attendance.forEach(record => {
      const key = record.course_code;
      if (!byCourse[key]) {
        byCourse[key] = {
          course_code: record.course_code,
          course_name: record.course_name,
          records: []
        };
      }
      byCourse[key].records.push(record);
    });
    
    // Calculate statistics
    let html = '<div style="margin-bottom: 30px;">';
    
    Object.values(byCourse).forEach(course => {
      const total = course.records.length;
      const present = course.records.filter(r => r.status === 'present').length;
      const absent = course.records.filter(r => r.status === 'absent').length;
      const late = course.records.filter(r => r.status === 'late').length;
      const excused = course.records.filter(r => r.status === 'excused').length;
      const rate = total > 0 ? Math.round((present + late) / total * 100) : 0;
      
      html += `
        <div class="course-card" style="margin-bottom: 20px; border-left: 4px solid ${rate >= 75 ? '#28a745' : rate >= 50 ? '#ffc107' : '#dc3545'};">
          <h3>${course.course_code} - ${course.course_name}</h3>
          <div style="display: flex; gap: 20px; margin: 15px 0;">
            <div><strong>Total Sessions:</strong> ${total}</div>
            <div style="color: #28a745;"><strong>Present:</strong> ${present}</div>
            <div style="color: #dc3545;"><strong>Absent:</strong> ${absent}</div>
            <div style="color: #ffc107;"><strong>Late:</strong> ${late}</div>
            <div style="color: #6c757d;"><strong>Excused:</strong> ${excused}</div>
            <div><strong>Attendance Rate:</strong> <span style="font-size: 1.2em; color: ${rate >= 75 ? '#28a745' : rate >= 50 ? '#ffc107' : '#dc3545'};">${rate}%</span></div>
          </div>
          
          <table style="margin-top: 15px;">
            <thead>
              <tr>
                <th>Session #</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Marked</th>
              </tr>
            </thead>
            <tbody>
      `;
      
      course.records.sort((a, b) => b.session_number - a.session_number).forEach(record => {
        const statusClass = 
          record.status === 'present' ? 'status-approved' :
          record.status === 'absent' ? 'status-rejected' :
          record.status === 'late' ? 'status-pending' : '';
        
        const markedDate = record.marked_at ? new Date(record.marked_at).toLocaleString() : 'N/A';
        
        html += `
          <tr>
            <td>${record.session_number}</td>
            <td>${formatDate(record.date)}</td>
            <td>${record.start_time} - ${record.end_time}</td>
            <td><span class="status-badge ${statusClass}">${record.status.toUpperCase()}</span></td>
            <td><small>${markedDate}</small></td>
          </tr>
        `;
      });
      
      html += `
            </tbody>
          </table>
        </div>
      `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    
  } catch (error) {
    const container = document.getElementById('table-container');
    container.innerHTML = `<p style="color:red;">Error loading attendance: ${error.message}</p>`;
  }
}

function formatDate(dateStr) {
  const date = new Date(dateStr);
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  return date.toLocaleDateString('en-US', options);
}
