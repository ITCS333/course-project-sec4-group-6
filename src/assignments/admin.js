let assignments = [];

const form = document.getElementById("assignment-form");
const tbody = document.getElementById("assignments-tbody");
const submitBtn = document.getElementById("add-assignment");

function createAssignmentRow(assignment) {
  const tr = document.createElement("tr");
  tr.innerHTML = `
    <td>${assignment.title}</td>
    <td>${assignment.due_date}</td>
    <td>${assignment.description}</td>
    <td>
      <button class="edit-btn" data-id="${assignment.id}">Edit</button>
      <button class="delete-btn" data-id="${assignment.id}">Delete</button>
    </td>
  `;
  return tr;
}

function renderTable() {
  tbody.innerHTML = "";
  assignments.forEach((assignment) => {
    const row = createAssignmentRow(assignment);
    tbody.appendChild(row);
  });
}

async function handleAddAssignment(event) {
  event.preventDefault();

  const title = document.getElementById("assignment-title").value;
  const due_date = document.getElementById("assignment-due-date").value;
  const description = document.getElementById("assignment-description").value;
  const filesText = document.getElementById("assignment-files").value;
  
  const files = filesText
    .split("\n")
    .map(f => f.trim())
    .filter(f => f !== "");

  const editId = submitBtn.dataset.editId;

  if (editId) {
    await handleUpdateAssignment(parseInt(editId), {
      title,
      due_date,
      description,
      files
    });
    return;
  }

  const response = await fetch("./api/index.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ title, due_date, description, files })
  });

  const result = await response.json();

  if (result.success) {
    assignments.push({ id: result.id, title, due_date, description, files });
    renderTable();
    form.reset();
  }
}

async function handleUpdateAssignment(id, fields) {
  const response = await fetch("./api/index.php", {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id, ...fields })
  });

  const result = await response.json();

  if (result.success) {
    assignments = assignments.map(a => a.id === id ? { ...a, ...fields } : a);
    renderTable();
    form.reset();
    submitBtn.textContent = "Add Assignment";
    delete submitBtn.dataset.editId;
  }
}

async function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains("delete-btn")) {
    const id = parseInt(target.dataset.id);
    const response = await fetch(`./api/index.php?id=${id}`, { method: "DELETE" });
    const result = await response.json();

    if (result.success) {
      assignments = assignments.filter(a => a.id !== id);
      renderTable();
    }
  }

  if (target.classList.contains("edit-btn")) {
    const id = parseInt(target.dataset.id);
    const assignment = assignments.find(a => a.id === id);

    if (assignment) {
      document.getElementById("assignment-title").value = assignment.title;
      document.getElementById("assignment-due-date").value = assignment.due_date;
      document.getElementById("assignment-description").value = assignment.description;
      document.getElementById("assignment-files").value = (assignment.files || []).join("\n");
      submitBtn.textContent = "Update Assignment";
      submitBtn.dataset.editId = id;
    }
  }
}

async function loadAndInitialize() {
  try {
    const response = await fetch("./api/index.php");
    const result = await response.json();

    if (result.success) {
      assignments = result.data.map(a => ({ ...a, files: a.files || [] }));
      renderTable();
    }
  } catch (error) {
    console.error(error);
  }

  form.addEventListener("submit", handleAddAssignment);
  tbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
