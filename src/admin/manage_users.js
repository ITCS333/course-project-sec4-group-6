
let users = [];


const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const passwordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");
// --- Functions ---


function createUserRow(user) {
  const tr = document.createElement("tr");

  const nameTd = document.createElement("td");
  nameTd.textContent = user.name;

  const emailTd = document.createElement("td");
  emailTd.textContent = user.email;

  const adminTd = document.createElement("td");
  adminTd.textContent = user.is_admin === 1 ? "Yes" : "No";

  const actionsTd = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.textContent = "Edit";
  editBtn.classList.add("edit-btn");
  editBtn.setAttribute("data-id", user.id);

  const deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.classList.add("delete-btn");
  deleteBtn.setAttribute("data-id", user.id);

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  tr.appendChild(nameTd);
  tr.appendChild(emailTd);
  tr.appendChild(adminTd);
  tr.appendChild(actionsTd);

  return tr;
}


function renderTable(userArray) {
  userTableBody.innerHTML = "";
    userArray.forEach(user => {
    const row = createUserRow(user);
    userTableBody.appendChild(row);
  });

}


function handleChangePassword(event) {
  event.preventDefault();

  const currentPasswordInput = document.getElementById("current-password");
  const newPasswordInput = document.getElementById("new-password");
  const confirmPasswordInput = document.getElementById("confirm-password");

  const currentPassword = currentPasswordInput.value.trim();
  const newPassword = newPasswordInput.value.trim();
  const confirmPassword = confirmPasswordInput.value.trim();

  if (newPassword !== confirmPassword) {
    alert("Passwords do not match.");
    return;
  }

  if (newPassword.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  fetch("../api/index.php?action=change_password", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      id: 1,
      current_password: currentPassword,
      new_password: newPassword
    })
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        alert("Password updated successfully!");
      } else {
        alert(result.message);
      }
    })
    .catch(error => {
      console.error("Error:", error);
    });

  currentPasswordInput.value = "";
  newPasswordInput.value = "";
  confirmPasswordInput.value = "";
}


function handleAddUser(event) {
  event.preventDefault();

  const name = document.getElementById("user-name").value.trim();
  const email = document.getElementById("user-email").value.trim();
  const password = document.getElementById("default-password").value.trim();
  const isAdmin = document.getElementById("is-admin").value;

  if (!name || !email || !password) {
    alert("Please fill out all required fields.");
    return;
  }

  if (password.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  fetch("../api/index.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      name: name,
      email: email,
      password: password,
      is_admin: Number(isAdmin)
    })
  })
    .then(response =>
      response.json().then(result => ({
        status: response.status,
        body: result
      }))
    )
    .then(data => {

      if (data.status === 201 && data.body.success) {
        loadUsersAndInitialize();

        addUserForm.reset();
      } else {
        alert(data.body.message);
      }
    })
    .catch(error => {
      console.error("Error adding user:", error);
      alert("Something went wrong.");
    });}


function handleTableClick(event) {
 const target = event.target;

  // delete
  if (target.classList.contains("delete-btn")) {
    const id = target.dataset.id;

    fetch("../api/index.php?id=" + id, {
      method: "DELETE"
    })
      .then(response => response.json())
      .then(result => {
        if (result.success) {
          users = users.filter(user => String(user.id) !== String(id));
          renderTable(users);
        } else {
          alert(result.message);
        }
      })
      .catch(error => {
        console.error("Error deleting user:", error);
        alert("Something went wrong.");
      });
  }

  if (target.classList.contains("edit-btn")) {
    const id = target.dataset.id;
    const user = users.find(user => String(user.id) === String(id));

    if (!user) {
      alert("User not found.");
      return;
    }

    const updatedName = prompt("Enter new name:", user.name);
    const updatedEmail = prompt("Enter new email:", user.email);
    const updatedIsAdmin = prompt("Enter admin status (1 for Admin, 0 for Student):", user.is_admin);

    if (updatedName === null || updatedEmail === null || updatedIsAdmin === null) {
      return;
    }

    fetch("../api/index.php", {
      method: "PUT",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        id: Number(id),
        name: updatedName.trim(),
        email: updatedEmail.trim(),
        is_admin: Number(updatedIsAdmin)
      })
    })
      .then(response => response.json())
      .then(result => {
        if (result.success) {
          loadUsersAndInitialize();
        } else {
          alert(result.message);
        }
      })
      .catch(error => {
        console.error("Error updating user:", error);
        alert("Something went wrong.");
      });
  }
}


function handleSearch(event) {
  const searchTerm = searchInput.value.toLowerCase();

  if (!searchTerm) {
    renderTable(users);
    return;
  }

  const filteredUsers = users.filter(user => {
    return (
      user.name.toLowerCase().includes(searchTerm) ||
      user.email.toLowerCase().includes(searchTerm)
    );
  });

  renderTable(filteredUsers);}


function handleSort(event) {
  const th = event.currentTarget;

  const index = th.cellIndex;

  let key;
  if (index === 0) key = "name";
  else if (index === 1) key = "email";
  else if (index === 2) key = "is_admin";
  else return; 

  let direction = th.getAttribute("data-sort-dir") === "asc" ? "desc" : "asc";
  th.setAttribute("data-sort-dir", direction);

  users.sort((a, b) => {
    let result;

    if (key === "name" || key === "email") {
      result = a[key].localeCompare(b[key]);
    } else {
      result = a[key] - b[key];
    }

    return direction === "asc" ? result : -result;
  });

  renderTable(users);
}


async function loadUsersAndInitialize() {
 try {
    const response = await fetch("../api/index.php");

    if (!response.ok) {
      console.error("Failed to fetch users:", response.statusText);
      alert("Failed to load users.");
      return;
    }

    const result = await response.json();

    users = result.data;

    renderTable(users);

    if (!loadUsersAndInitialize.initialized) {
      passwordForm.addEventListener("submit", handleChangePassword);
      addUserForm.addEventListener("submit", handleAddUser);
      userTableBody.addEventListener("click", handleTableClick);
      searchInput.addEventListener("input", handleSearch);

      tableHeaders.forEach(th => {
        th.addEventListener("click", handleSort);
      });

      loadUsersAndInitialize.initialized = true;
    }
  } catch (error) {
    console.error("Error loading users:", error);
    alert("Failed to load users.");
  }
}

loadUsersAndInitialize();