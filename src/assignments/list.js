/*
  Requirement: Populate the "Course Assignments" list page.

  Instructions:
  1. This file is already linked to `list.html` via:
         <script src="list.js" defer></script>

  2. In `list.html`, the <section id="assignment-list-section"> is the
     container that this script populates.

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  Successful list response shape: { success: true, data: [ ...assignment objects ] }
  Each assignment object shape:
    {
      id:          number,   // integer primary key from the assignments table
      title:       string,
      due_date:    string,   // "YYYY-MM-DD" — matches the SQL column name
      description: string,
      files:       string[]  // already decoded array of URL strings
    }
*/

// --- Element Selections ---
const assignmentListSection = document.getElementById("assignment-list-section");

// --- Functions ---

function createAssignmentArticle(assignment) {
  const article = document.createElement("article");

  article.innerHTML = `
    <h2>${assignment.title}</h2>
    <p>Due: ${assignment.due_date}</p>
    <p>${assignment.description}</p>
    <a href="details.html?id=${assignment.id}">
      View Details & Discussion
    </a>
  `;

  return article;
}

async function loadAssignments() {
  const response = await fetch("./api/index.php");
  const result = await response.json();

  assignmentListSection.innerHTML = "";

  if (result.success) {
    result.data.forEach((assignment) => {
      const article = createAssignmentArticle(assignment);
      assignmentListSection.appendChild(article);
    });
  }
}

// --- Initial Page Load ---
loadAssignments();
