/*
  list.js — Populates the "Course Assignments" list page.
  API base URL: ./api/index.php
*/

// --- Element Selections ---
const assignmentListSection = document.getElementById('assignment-list-section');

// --- Functions ---

/**
 * Builds and returns an <article> element for one assignment.
 */
function createAssignmentArticle(assignment) {
  const article = document.createElement('article');

  const h2 = document.createElement('h2');
  h2.textContent = assignment.title;

  const dueDate = document.createElement('p');
  dueDate.textContent = 'Due: ' + assignment.due_date;

  const description = document.createElement('p');
  description.textContent = assignment.description;

  const link = document.createElement('a');
  link.href        = `details.html?id=${assignment.id}`;
  link.textContent = 'View Details & Discussion';

  article.appendChild(h2);
  article.appendChild(dueDate);
  article.appendChild(description);
  article.appendChild(link);

  return article;
}

/**
 * Fetches all assignments from the API and renders them into the page.
 */
async function loadAssignments() {
  try {
    const response = await fetch('./api/index.php');
    const result   = await response.json();

    assignmentListSection.innerHTML = '';

    if (result.success) {
      result.data.forEach(assignment => {
        assignmentListSection.appendChild(createAssignmentArticle(assignment));
      });
    } else {
      console.error('Failed to load assignments:', result);
    }
  } catch (err) {
    console.error('Error loading assignments:', err);
  }
}

// --- Initial Page Load ---
loadAssignments();
