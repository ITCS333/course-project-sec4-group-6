/*
  Admin JS — full CRUD for the "Manage Assignments" page.
  API base URL: ./api/index.php  (all requests/responses use JSON)
*/

// --- Global Data Store ---
let assignments = [];

// --- Element Selections ---
const assignmentForm   = document.getElementById('assignment-form');
const assignmentsTbody = document.getElementById('assignments-tbody');

// --- Functions ---

/**
 * Builds and returns a <tr> for one assignment object.
 */
function createAssignmentRow(assignment) {
  const tr = document.createElement('tr');

  // 1. Title
  const titleTd = document.createElement('td');
  titleTd.textContent = assignment.title;

  // 2. Due date (raw "YYYY-MM-DD" string from the API)
  const dateTd = document.createElement('td');
  dateTd.textContent = assignment.due_date;

  // 3. Description
  const descTd = document.createElement('td');
  descTd.textContent = assignment.description;

  // 4. Actions
  const actionsTd = document.createElement('td');

  const editBtn = document.createElement('button');
  editBtn.className  = 'edit-btn';
  editBtn.dataset.id = assignment.id;
  editBtn.textContent = 'Edit';

  const deleteBtn = document.createElement('button');
  deleteBtn.className  = 'delete-btn';
  deleteBtn.dataset.id = assignment.id;
  deleteBtn.textContent = 'Delete';

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  tr.appendChild(titleTd);
  tr.appendChild(dateTd);
  tr.appendChild(descTd);
  tr.appendChild(actionsTd);

  return tr;
}

/**
 * Clears the tbody and re-renders every assignment from the global array.
 */
function renderTable() {
  assignmentsTbody.innerHTML = '';
  assignments.forEach(assignment => {
    assignmentsTbody.appendChild(createAssignmentRow(assignment));
  });
}

/**
 * Reads form fields and resets the submit button to its default state.
 * Returns { title, due_date, description, files }.
 */
function readFormFields() {
  const title       = document.getElementById('assignment-title').value.trim();
  const due_date    = document.getElementById('assignment-due-date').value;
  const description = document.getElementById('assignment-description').value.trim();
  const files       = document.getElementById('assignment-files').value
                        .split('\n')
                        .map(url => url.trim())
                        .filter(url => url !== '');
  return { title, due_date, description, files };
}

function resetSubmitButton() {
  const btn = document.getElementById('add-assignment');
  btn.textContent = 'Add Assignment';
  delete btn.dataset.editId;
}

/**
 * Submit handler — decides between create and update.
 */
async function handleAddAssignment(event) {
  event.preventDefault();

  const fields = readFormFields();
  const submitBtn = document.getElementById('add-assignment');

  if (submitBtn.dataset.editId) {
    // Edit mode — delegate to update handler
    await handleUpdateAssignment(Number(submitBtn.dataset.editId), fields);
  } else {
    // Create mode — POST new assignment
    try {
      const response = await fetch('./api/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(fields),
      });

      const result = await response.json();

      if (result.success) {
        assignments.push({ id: result.id, ...fields });
        renderTable();
        assignmentForm.reset();
      } else {
        console.error('Failed to add assignment:', result);
      }
    } catch (err) {
      console.error('Error adding assignment:', err);
    }
  }
}

/**
 * Sends a PUT request to update an existing assignment.
 */
async function handleUpdateAssignment(id, fields) {
  try {
    const response = await fetch('./api/index.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, ...fields }),
    });

    const result = await response.json();

    if (result.success) {
      // Update the matching entry in the global array
      const index = assignments.findIndex(a => a.id === id);
      if (index !== -1) {
        assignments[index] = { id, ...fields };
      }
      renderTable();
      assignmentForm.reset();
      resetSubmitButton();
    } else {
      console.error('Failed to update assignment:', result);
    }
  } catch (err) {
    console.error('Error updating assignment:', err);
  }
}

/**
 * Delegated click handler for Edit and Delete buttons in the table.
 */
async function handleTableClick(event) {
  const target = event.target;

  // ── DELETE ──
  if (target.classList.contains('delete-btn')) {
    const id = Number(target.dataset.id);

    try {
      const response = await fetch(`./api/index.php?id=${id}`, {
        method: 'DELETE',
      });

      const result = await response.json();

      if (result.success) {
        assignments = assignments.filter(a => a.id !== id);
        renderTable();
      } else {
        console.error('Failed to delete assignment:', result);
      }
    } catch (err) {
      console.error('Error deleting assignment:', err);
    }
    return;
  }

  // ── EDIT ──
  if (target.classList.contains('edit-btn')) {
    const id         = Number(target.dataset.id);
    const assignment = assignments.find(a => a.id === id);
    if (!assignment) return;

    // Populate form fields
    document.getElementById('assignment-title').value       = assignment.title;
    document.getElementById('assignment-due-date').value    = assignment.due_date;
    document.getElementById('assignment-description').value = assignment.description;
    document.getElementById('assignment-files').value       = assignment.files.join('\n');

    // Switch submit button to edit mode
    const submitBtn = document.getElementById('add-assignment');
    submitBtn.textContent    = 'Update Assignment';
    submitBtn.dataset.editId = assignment.id;

    // Scroll form into view on mobile
    assignmentForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

/**
 * Fetches all assignments, populates the table, and wires up event listeners.
 */
async function loadAndInitialize() {
  try {
    const response = await fetch('./api/index.php');
    const result   = await response.json();

    if (result.success) {
      assignments = result.data;
      renderTable();
    } else {
      console.error('Failed to load assignments:', result);
    }
  } catch (err) {
    console.error('Error loading assignments:', err);
  }

  // Attach event listeners
  assignmentForm.addEventListener('submit', handleAddAssignment);
  assignmentsTbody.addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
loadAndInitialize();
