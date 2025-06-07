
 
  if (!isset($_SESSION['edit_task_id'])) {
    $_SESSION['edit_task_id'] = null;
    $_SESSION['edit_section'] = null;
  }

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
    // You might want to add an AJAX call here to clear the session variables
  }

  // Show edit modal if URL has edit=1
  window.addEventListener('DOMContentLoaded', function() {
    if (window.location.search.includes('edit=1')) {
        openEditTaskModal();
    }
  });

  function handleUpdateSubmit() {
    // You can add validation here if needed
    return true; // Allow form submission
  }
 
  function closeAddTaskModal() {
    document.getElementById('addTaskModal').style.display = 'none';
    // Remove edit parameter from URL
    if (window.location.search.includes('edit=1')) {
        history.replaceState(null, '', window.location.pathname + '?section=<?= $section ?>');
    }
  }

  // In your update form submission success handler
  function handleUpdateSuccess() {
    closeEditTaskModal();
    // Force a reload of the current section
    window.location.reload();
  }

  
// Show edit modal if URL has edit=1
  if (window.location.search.includes('edit=1')) {
    openEditTaskModal();
  }

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

  updateDateTime();
  setInterval(updateDateTime, 1000);
 // Replace your existing toggleSortOptions and related functions with this:

function toggleSortOptions() {
    const sortOptions = document.getElementById('sorting-options');
    if (sortOptions.style.display === 'none') {
        sortOptions.style.display = 'block';
    } else {
        sortOptions.style.display = 'none';
    }
}

document.getElementById('sort-by').addEventListener('change', function() {
    const sortValue = this.value;
    const url = new URL(window.location);
    
    if (sortValue) {
        url.searchParams.set('sort', sortValue);
    } else {
        url.searchParams.delete('sort');
    }
    console.log(sortValue);
    window.location.href = url.toString();
});

// Close sort dropdown when clicking outside
document.addEventListener('click', function(event) {
    const sortOptions = document.getElementById('sorting-options');
    const sortButton = document.querySelector('button[onclick="toggleSortOptions()"]');
    const sortSelect = document.getElementById('sort-by');
    
    if (!sortOptions.contains(event.target) && 
        event.target !== sortButton && 
        event.target !== sortSelect) {
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
});