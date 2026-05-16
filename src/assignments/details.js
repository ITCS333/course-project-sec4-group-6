/*
  details.js — Populates the assignment detail page and discussion forum.
  API base URL: ./api/index.php
*/

// --- Global Data Store ---
let currentAssignmentId = null;
let currentComments     = [];

// --- Element Selections ---
const assignmentTitle       = document.getElementById('assignment-title');
const assignmentDueDate     = document.getElementById('assignment-due-date');
const assignmentDescription = document.getElementById('assignment-description');
const assignmentFilesList   = document.getElementById('assignment-files-list');
const commentList           = document.getElementById('comment-list');
const commentForm           = document.getElementById('comment-form');
const newCommentInput       = document.getElementById('new-comment');

// --- Functions ---

/**
 * Reads the 'id' query parameter from the current page URL.
 * Returns the string value, or null if absent.
 */
function getAssignmentIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

/**
 * Fills all assignment detail elements with data from the API response.
 */
function renderAssignmentDetails(assignment) {
  assignmentTitle.textContent       = assignment.title;
  assignmentDueDate.textContent     = 'Due: ' + assignment.due_date;
  assignmentDescription.textContent = assignment.description;

  // Rebuild the files list
  assignmentFilesList.innerHTML = '';
  (assignment.files ?? []).forEach(url => {
    const li = document.createElement('li');
    const a  = document.createElement('a');
    a.href        = url;
    a.textContent = url;
    li.appendChild(a);
    assignmentFilesList.appendChild(li);
  });
}

/**
 * Creates and returns an <article> element for a single comment.
 */
function createCommentArticle(comment) {
  const article = document.createElement('article');

  const p = document.createElement('p');
  p.textContent = comment.text;

  const footer = document.createElement('footer');
  footer.textContent = 'Posted by: ' + comment.author;

  article.appendChild(p);
  article.appendChild(footer);

  return article;
}

/**
 * Clears the comment list and re-renders every comment in currentComments.
 */
function renderComments() {
  commentList.innerHTML = '';
  currentComments.forEach(comment => {
    commentList.appendChild(createCommentArticle(comment));
  });
}

/**
 * Submit handler — posts a new comment to the API.
 */
async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newCommentInput.value.trim();
  if (!commentText) return;

  try {
    const response = await fetch('./api/index.php?action=comment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        assignment_id: currentAssignmentId,
        author:        'Student',
        text:          commentText,
      }),
    });

    const result = await response.json();

    if (result.success) {
      currentComments.push(result.data);
      renderComments();
      newCommentInput.value = '';
    } else {
      console.error('Failed to post comment:', result);
    }
  } catch (err) {
    console.error('Error posting comment:', err);
  }
}

/**
 * Fetches the assignment and its comments in parallel, then renders the page.
 */
async function initializePage() {
  currentAssignmentId = getAssignmentIdFromURL();

  if (!currentAssignmentId) {
    assignmentTitle.textContent = 'Assignment not found.';
    return;
  }

  try {
    const [assignmentRes, commentsRes] = await Promise.all([
      fetch(`./api/index.php?id=${currentAssignmentId}`),
      fetch(`./api/index.php?action=comments&assignment_id=${currentAssignmentId}`),
    ]);

    const [assignmentResult, commentsResult] = await Promise.all([
      assignmentRes.json(),
      commentsRes.json(),
    ]);

    currentComments = commentsResult.success ? commentsResult.data : [];

    if (assignmentResult.success) {
      renderAssignmentDetails(assignmentResult.data);
      renderComments();
      commentForm.addEventListener('submit', handleAddComment);
    } else {
      assignmentTitle.textContent = 'Assignment not found.';
    }
  } catch (err) {
    console.error('Error loading page:', err);
    assignmentTitle.textContent = 'Assignment not found.';
  }
}

// --- Initial Page Load ---
initializePage();
