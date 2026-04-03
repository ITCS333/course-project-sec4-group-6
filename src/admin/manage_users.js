/*
  Requirement: Add interactivity and data management to the Admin Portal.

  Instructions:
  1. This file is loaded by the <script src="manage_users.js" defer> tag in manage_users.html.
     The 'defer' attribute guarantees the DOM is fully parsed before this script runs.
  2. Implement the JavaScript functionality as described in the TODO comments.
  3. All data is fetched from and written to the PHP API at '../api/index.php'.
     The local 'users' array is used only as a client-side cache for search and sort.
*/

// --- Global Data Store ---
// This array will be populated with data fetched from the PHP API.
// It acts as a client-side cache so search and sort work without extra network calls.
let users = [];

// --- Element Selections ---
// We can safely select elements here because 'defer' guarantees
// the HTML document is parsed before this script runs.

const userTableBody = document.getElementById("user-table-body");

const addUserForm = document.getElementById("add-user-form");

const passwordForm = document.getElementById("password-form");

const searchInput = document.getElementById("search-input");

const tableHeaders = document.querySelectorAll("#user-table thead th");
// --- Functions ---

/**
 * TODO: Implement the createUserRow function.
 * This function takes a user object { id, name, email, is_admin } and returns a <tr> element.
 * The <tr> should contain:
 * 1. A <td> for the user's name.
 * 2. A <td> for the user's email.
 * 3. A <td> showing admin status, e.g. "Yes" if is_admin === 1, otherwise "No".
 * 4. A <td> containing two buttons:
 *    - An "Edit" button with class "edit-btn" and a data-id attribute set to the user's id.
 *    - A "Delete" button with class "delete-btn" and a data-id attribute set to the user's id.
 */
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

/**
 * TODO: Implement the renderTable function.
 * This function takes an array of user objects.
 * It should:
 * 1. Clear the current content of the userTableBody.
 * 2. Loop through the provided array of users.
 * 3. For each user, call createUserRow and append the returned <tr> to userTableBody.
 */
function renderTable(userArray) {
  userTableBody.innerHTML = "";
    userArray.forEach(user => {
    const row = createUserRow(user);
    userTableBody.appendChild(row);
  });

}

/**
 * TODO: Implement the handleChangePassword function.
 * This function is called when the "Update Password" form is submitted.
 * It should:
 * 1. Prevent the form's default submission behaviour.
 * 2. Get the values from "current-password", "new-password", and "confirm-password" inputs.
 * 3. Perform client-side validation:
 *    - If "new-password" and "confirm-password" do not match, show an alert: "Passwords do not match."
 *    - If "new-password" is less than 8 characters, show an alert: "Password must be at least 8 characters."
 * 4. If validation passes, send a POST request to '../api/index.php?action=change_password'
 *    with a JSON body: { id, current_password, new_password }
 *    where 'id' is the currently logged-in admin's user id.
 * 5. On success, show an alert: "Password updated successfully!" and clear all three inputs.
 * 6. On failure, show the error message returned by the API.
 */
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

/**
 * TODO: Implement the handleAddUser function.
 * This function is called when the "Add User" form is submitted.
 * It should:
 * 1. Prevent the form's default submission behaviour.
 * 2. Get the values from "user-name", "user-email", "default-password", and "is-admin".
 * 3. Perform client-side validation:
 *    - If name, email, or password are empty, show an alert: "Please fill out all required fields."
 *    - If password is less than 8 characters, show an alert: "Password must be at least 8 characters."
 * 4. If validation passes, send a POST request to '../api/index.php'
 *    with a JSON body: { name, email, password, is_admin }
 * 5. On success (HTTP 201), re-fetch the full user list by calling loadUsersAndInitialize()
 *    so the table reflects the new record from the database.
 * 6. Clear the form inputs on success.
 * 7. On failure, show the error message returned by the API.
 */
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

/**
 * TODO: Implement the handleTableClick function.
 * This function is an event listener on userTableBody (event delegation).
 * It should:
 * 1. Check if the clicked element has the class "delete-btn".
 * 2. If it is a "delete-btn":
 *    - Get the data-id attribute from the button (this is the user's database id).
 *    - Send a DELETE request to '../api/index.php?id=' + id.
 *    - On success, remove the user from the local 'users' array and call renderTable(users).
 *    - On failure, show the error message returned by the API.
 * 3. If it is an "edit-btn":
 *    - Get the data-id attribute from the button.
 *    - (Optional) Populate an edit form or prompt with the user's current data
 *      and send a PUT request to '../api/index.php' with the updated fields.
 */
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

  // edit
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

/**
 * TODO: Implement the handleSearch function.
 * This function is called on the "input" event of the searchInput.
 * It should:
 * 1. Get the search term from searchInput.value and convert it to lowercase.
 * 2. If the search term is empty, call renderTable(users) to show all users.
 * 3. Otherwise, filter the local 'users' array to find users whose name or email
 *    (converted to lowercase) includes the search term.
 * 4. Call renderTable with the filtered array.
 *    (This filters the client-side cache only; no extra API call is needed.)
 */
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

/**
 * TODO: Implement the handleSort function.
 * This function is called when any <th> in the thead is clicked.
 * It should:
 * 1. Identify which column was clicked using event.currentTarget.cellIndex.
 * 2. Map the cell index to a property name:
 *    - index 0 -> 'name'
 *    - index 1 -> 'email'
 *    - index 2 -> 'is_admin'
 * 3. Toggle sort direction using a data-sort-dir attribute on the <th>
 *    between "asc" and "desc".
 * 4. Sort the local 'users' array in place using array.sort():
 *    - For 'name' and 'email', use localeCompare for string comparison.
 *    - For 'is_admin', compare the values as numbers.
 * 5. Respect the sort direction (ascending or descending).
 * 6. Call renderTable(users) to update the view.
 */
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

/**
 * TODO: Implement the loadUsersAndInitialize function.
 * This function must be async.
 * It should:
 * 1. Send a GET request to '../api/index.php' using fetch().
 * 2. Check if the response is ok. If not, log the error and show an alert.
 * 3. Parse the JSON response: await response.json().
 *    The API returns { success: true, data: [ ...users ] }.
 * 4. Assign the data array to the global 'users' variable.
 * 5. Call renderTable(users) to populate the table.
 * 6. Attach all event listeners (only on the first call, or use { once: true } where appropriate):
 *    - "submit" on changePasswordForm  -> handleChangePassword
 *    - "submit" on addUserForm         -> handleAddUser
 *    - "click"  on userTableBody       -> handleTableClick
 *    - "input"  on searchInput         -> handleSearch
 *    - "click"  on each th in tableHeaders -> handleSort
 */
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

// --- Initial Page Load ---
loadUsersAndInitialize();