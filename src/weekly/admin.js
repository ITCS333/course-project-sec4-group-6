/*
  Requirement: Make the "Manage Weekly Breakdown" page interactive.

  Instructions:
  1. This file is already linked to `admin.html` via:
         <script src="admin.js" defer></script>

  2. In `admin.html`:
     - The form has id="week-form".
     - The submit button has id="add-week".
     - The <tbody> has id="weeks-tbody".
     - Columns rendered per row: Week Title | Start Date | Description | Actions.

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  All requests and responses use JSON.
  Successful list response shape: { success: true, data: [ ...week objects ] }
  Each week object shape:
    {
      id:          number,   // integer primary key from the weeks table
      title:       string,
      start_date:  string,   // "YYYY-MM-DD"
      description: string,
      links:       string[]  // decoded array of URL strings
    }
*/

// --- Global Data Store ---
// Holds the weeks currently displayed in the table.
let weeks = [];

// --- Element Selections ---
const weekForm = document.getElementById('week-form');

const weeksTbody = document.getElementById('weeks-tbody');

// --- Functions ---

/**
 * TODO: Implement createWeekRow.
 *
 * Parameters:
 *   week — one week object with shape:
 *     { id, title, start_date, description, links }
 *
 * Returns a <tr> element with four <td>s:
 *   1. title
 *   2. start_date  (the "YYYY-MM-DD" string from the weeks table)
 *   3. description
 *   4. Actions — two buttons:
 *        <button class="edit-btn"   data-id="{id}">Edit</button>
 *        <button class="delete-btn" data-id="{id}">Delete</button>
 *      The data-id holds the integer primary key from the weeks table.
 */
function createWeekRow(week) {
const tr = document.createElement('tr');

    tr.innerHTML = `
        <td>${week.title}</td>
        <td>${week.start_date}</td>
        <td>${week.description}</td>
        <td>
            <button class="edit-btn" data-id="${week.id}">Edit</button>
            <button class="delete-btn" data-id="${week.id}">Delete</button>
        </td>
    `;

    return tr;
  }

/**
 * TODO: Implement renderTable.
 *
 * It should:
 * 1. Clear the weeks table body (set innerHTML to "").
 * 2. Loop through the global `weeks` array.
 * 3. For each week, call createWeekRow(week) and append the <tr>
 *    to the table body.
 */
function renderTable() {
 weeksTbody.innerHTML = "";

    weeks.forEach((week) => {
        const row = createWeekRow(week);
        weeksTbody.appendChild(row);
    });
  }

/**
 * TODO: Implement handleAddWeek (async).
 *
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Call event.preventDefault().
 * 2. Read values from:
 *      - #week-title       → title (string)
 *      - #week-start-date  → start_date (string, "YYYY-MM-DD")
 *      - #week-description → description (string)
 *      - #week-links       → split by newlines (\n) and filter empty
 *                            strings to produce a links array.
 * 3. Check if the submit button (#add-week) has a data-edit-id attribute.
 *    - If it does, call handleUpdateWeek() with that id and the field values.
 *    - If it does not, send a POST to './api/index.php' with the body:
 *        { title, start_date, description, links }
 *      On success (result.success === true):
 *        - Add the new week (with the id from result.id) to the global
 *          `weeks` array.
 *        - Call renderTable().
 *        - Reset the form.
 */
async function handleAddWeek(event) {
    event.preventDefault();

    const title = document.getElementById('week-title').value;
    const start_date = document.getElementById('week-start-date').value;
    const description = document.getElementById('week-description').value;

    const links = document
        .getElementById('week-links')
        .value
        .split('\n')
        .filter(link => link.trim() !== "");

    const addWeekButton = document.getElementById('add-week');
    const editId = addWeekButton.dataset.editId;

    if (editId) {
        await handleUpdateWeek(editId, {
            title,
            start_date,
            description,
            links
        });

        return;
    }

    const response = await fetch('./api/index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            title,
            start_date,
            description,
            links
        })
    });

    const result = await response.json();

    if (result.success === true) {
        weeks.push({
            id: result.id,
            title,
            start_date,
            description,
            links
        });

        renderTable();
        weekForm.reset();
    }
  }

/**
 * TODO: Implement handleUpdateWeek (async).
 *
 * Parameters:
 *   id     — the integer primary key of the week being edited.
 *   fields — object with { title, start_date, description, links }.
 *
 * It should:
 * 1. Send a PUT to './api/index.php' with the body:
 *      { id, title, start_date, description, links }
 * 2. On success:
 *    - Update the matching entry in the global `weeks` array.
 *    - Call renderTable().
 *    - Reset the form.
 *    - Restore the submit button text to "Add Week" and remove
 *      its data-edit-id attribute.
 */
async function handleUpdateWeek(id, fields) {
 const response = await fetch('./api/index.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id,
            ...fields
        })
    });

    const result = await response.json();

    if (result.success === true) {
        weeks = weeks.map((week) => {
            if (week.id == id) {
                return {
                    id: Number(id),
                    ...fields
                };
            }

            return week;
        });

        renderTable();
        weekForm.reset();

        const addWeekButton = document.getElementById('add-week');
        addWeekButton.textContent = "Add Week";

        delete addWeekButton.dataset.editId;
    }
  }

/**
 * TODO: Implement handleTableClick (async).
 *
 * This is a delegated click listener on the weeks table body.
 * It should:
 * 1. If event.target has class "delete-btn":
 *    a. Read the integer id from event.target.dataset.id.
 *    b. Send a DELETE to './api/index.php?id=<id>'.
 *    c. On success, remove the week from the global `weeks` array
 *       and call renderTable().
 *
 * 2. If event.target has class "edit-btn":
 *    a. Read the integer id from event.target.dataset.id.
 *    b. Find the matching week in the global `weeks` array.
 *    c. Populate the form fields (#week-title, #week-start-date,
 *       #week-description, #week-links) with the week's data.
 *       For #week-links, join the links array with newlines (\n).
 *    d. Change the submit button (#add-week) text to "Update Week"
 *       and set its data-edit-id attribute to the week's id.
 */
async function handleTableClick(event) {
 if (event.target.classList.contains('delete-btn')) {
        const id = event.target.dataset.id;

        const response = await fetch(`./api/index.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success === true) {
            weeks = weeks.filter((week) => week.id != id);
            renderTable();
        }
    }

    if (event.target.classList.contains('edit-btn')) {
        const id = event.target.dataset.id;

        const week = weeks.find((week) => week.id == id);

        if (!week) {
            return;
        }

        document.getElementById('week-title').value = week.title;
        document.getElementById('week-start-date').value = week.start_date;
        document.getElementById('week-description').value = week.description;
        document.getElementById('week-links').value = week.links.join('\n');

        const addWeekButton = document.getElementById('add-week');
        addWeekButton.textContent = "Update Week";
        addWeekButton.dataset.editId = week.id;
    }
  }

/**
 * TODO: Implement loadAndInitialize (async).
 *
 * It should:
 * 1. Send a GET to './api/index.php'.
 *    Response shape: { success: true, data: [ ...week objects ] }
 * 2. Store the data array in the global `weeks` variable.
 * 3. Call renderTable() to populate the table.
 * 4. Attach the 'submit' event listener to the week form
 *    (calls handleAddWeek).
 * 5. Attach a 'click' event listener to the weeks table body
 *    (calls handleTableClick — event delegation for edit and delete).
 */
async function loadAndInitialize() {
 const response = await fetch('./api/index.php');
    const result = await response.json();

    if (result.success === true) {
        weeks = result.data;
        renderTable();
    }

    weekForm.addEventListener('submit', handleAddWeek);
    weeksTbody.addEventListener('click', handleTableClick);}

// --- Initial Page Load ---
loadAndInitialize();
