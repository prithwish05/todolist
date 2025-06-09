// Initialize session variables if not set
if (typeof $_SESSION === 'undefined') {
  var $_SESSION = {};
}
if (!$_SESSION['edit_task_id']) {
  $_SESSION['edit_task_id'] = null;
  $_SESSION['edit_section'] = null;
}

// Modal control functions
function openAddTaskModal() {
  document.getElementById('addTaskModal').style.display = 'flex';
}

function closeAddTaskModal() {
  document.getElementById('addTaskModal').style.display = 'none';
}

function openEditTaskModal() {
  document.getElementById('editTaskModal').style.display = 'flex';
}

function closeEditTaskModal() {
    document.getElementById('editTaskModal').style.display = 'none';
    // Clear the edit parameters from URL
    if (window.location.search.includes('edit=1')) {
        const url = new URL(window.location);
        url.searchParams.delete('edit');
        history.replaceState(null, '', url);
    }
    
    // Clear session variables via AJAX if needed
    fetch('clear_edit_session.php', { 
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    });
}

// Show edit modal if URL has edit=1
window.addEventListener('DOMContentLoaded', function() {
  if (window.location.search.includes('edit=1')) {
    openEditTaskModal();
  }
});

// Form handling functions
function handleUpdateSubmit() {
  // Validate form before submission
  const form = document.querySelector('#editTaskModal form');
  const taskName = form.elements['task_name'].value.trim();
  const description = form.elements['description'].value.trim();
  
  if (!taskName || !description) {
    alert('Please fill in all required fields');
    return false;
  }
  return true;
}

function handleUpdateSuccess() {
  closeEditTaskModal();
  // Refresh the current section
  const section = new URLSearchParams(window.location.search).get('section') || 'today';
  window.location.href = `dashboard.php?section=${section}`;
}

// Date/time display function (removed duplicate)
function updateDateTime() {
  const now = new Date();
  const days = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
  const dayName = days[now.getDay()];
  const day = String(now.getDate()).padStart(2, '0');
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const year = now.getFullYear();
  const hours = String(now.getHours()).padStart(2, '0');
  const minutes = String(now.getMinutes()).padStart(2, '0');
  const seconds = String(now.getSeconds()).padStart(2, '0');
  const formatted = `${dayName}, ${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
  document.getElementById('date-time').textContent = formatted;
}

// Initialize date time and update every second
updateDateTime();
setInterval(updateDateTime, 1000);

// Sorting functionality (removed duplicate)
function applySort() {
    const sortBy = document.getElementById('sort-by').value;
    const currentSection = '<?= $section ?>';
    window.location.href = `dashboard.php?section=${currentSection}&sort=${sortBy}`;
}

function toggleSortOptions() {
    const options = document.getElementById('sorting-options');
    options.style.display = options.style.display === 'none' ? 'block' : 'none';
}

document.getElementById('sort-by').addEventListener('change', function() {
  const sortValue = this.value;
  const url = new URL(window.location);
  
  if (sortValue) {
    url.searchParams.set('sort', sortValue);
  } else {
    url.searchParams.delete('sort');
  }
  window.location.href = url.toString();
});

document.addEventListener('click', function(event) {
  const sortOptions = document.getElementById('sorting-options');
  const sortButton = document.querySelector('button[onclick="toggleSortOptions()"]');
  
  if (!sortOptions.contains(event.target) && event.target !== sortButton) {
    sortOptions.style.display = 'none';
  }
});

// Set the selected sort option based on URL parameter
window.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  const sortParam = urlParams.get('sort');
  if (sortParam) {
    document.getElementById('sort-by').value = sortParam;
  }
  
  // Set min date for all date inputs
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  const minDate = `${year}-${month}-${day}`;
  
  // Apply to all date inputs in both modals
  document.querySelectorAll('input[type="date"]').forEach(input => {
      input.setAttribute('min', minDate);
  });
});

// Handle edit form submission
document.querySelector('#editTaskModal form')?.addEventListener('submit', function(e) {
  if (!handleUpdateSubmit()) {
    e.preventDefault();
    return;
  }
});

// Task completion functionality
document.querySelectorAll('.complete-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const checkbox = this.querySelector('.complete-checkbox');
        const taskRow = this.closest('tr');
        const formData = new FormData(this);
        const section = new URL(window.location.href).searchParams.get('section') || 'today';
        
        // Immediately disable the checkbox to prevent multiple submissions
        checkbox.disabled = true;
        
        // Show loading state
        const label = this.querySelector('.complete-label');
        label.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Submit form via AJAX
        fetch('dashboard.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                // Update the count in the UI without full page reload
                updateTaskCounts(section);
                window.location.reload(); // Still reload to ensure consistency
            } else {
                // Re-enable if there was an error
                checkbox.disabled = false;
                updateCheckboxLabel(checkbox);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            checkbox.disabled = false;
            updateCheckboxLabel(checkbox);
        });
    });
});

function updateTaskCounts(section) {
    // This would ideally be replaced with a proper API endpoint that returns counts
    // For now, we'll just update the UI based on the action
    const countElement = document.querySelector(`.tab[href*="section=${section}"] .count`);
    if (countElement) {
        const currentCount = parseInt(countElement.textContent);
        const newCount = isNaN(currentCount) ? 0 : currentCount + 1;
        countElement.textContent = newCount;
    }
    
    // Also update completed count if needed
    const completedCountElement = document.querySelector('.tab[href*="section=completed"] .count');
    if (completedCountElement) {
        const currentCompleted = parseInt(completedCountElement.textContent);
        if (!isNaN(currentCompleted)) {
            const action = formData.get('action');
            const newCompleted = action === 'complete' ? currentCompleted + 1 : Math.max(0, currentCompleted - 1);
            completedCountElement.textContent = newCompleted;
        }
    }
}

function updateCheckboxLabel(checkbox) {
    const label = checkbox.nextElementSibling;
    if (checkbox.checked) {
        label.innerHTML = '<i class="fas fa-check-circle checked-icon"></i>';
    } else {
        label.innerHTML = '<i class="fas fa-times-circle unchecked-icon"></i>';
    }
}

// Sidebar functionality
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('open');
    sidebar.classList.toggle('collapsed');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.mobile-menu-toggle');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(event.target) && 
        !toggleBtn.contains(event.target) &&
        sidebar.classList.contains('open')) {
        toggleSidebar();
    }
});



