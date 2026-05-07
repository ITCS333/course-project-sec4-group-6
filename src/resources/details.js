let currentResourceId = null;
let currentComments = [];

// --- Element Selections ---

const resourceTitle =
  document.querySelector("#resource-title");

const resourceDescription =
  document.querySelector("#resource-description");

const resourceLink =
  document.querySelector("#resource-link");

const commentList =
  document.querySelector("#comment-list");

const commentForm =
  document.querySelector("#comment-form");

const newComment =
  document.querySelector("#new-comment");

// --- Functions ---

function getResourceIdFromURL() {

  const queryString =
    window.location.search;

  const params =
    new URLSearchParams(queryString);

  return params.get("id");
}

function renderResourceDetails(resource) {

  resourceTitle.textContent =
    resource.title;

  resourceDescription.textContent =
    resource.description;

  resourceLink.href =
    resource.link;
}

function createCommentArticle(comment) {

  const article =
    document.createElement("article");

  article.innerHTML = `
    <p>${comment.text}</p>

    <footer>
      Posted by: ${comment.author}
    </footer>
  `;

  return article;
}

function renderComments() {

  commentList.innerHTML = "";

  currentComments.forEach(comment => {

    const article =
      createCommentArticle(comment);

    commentList.appendChild(article);
  });
}

async function handleAddComment(event) {

  event.preventDefault();

  const commentText =
    newComment.value.trim();

  if (!commentText) {
    return;
  }

  const response =
    await fetch("./api/index.php?action=comment", {
      method: "POST",

      headers: {
        "Content-Type": "application/json"
      },

      body: JSON.stringify({
        resource_id: currentResourceId,
        author: "Student",
        text: commentText
      })
    });

  const result =
    await response.json();

  currentComments.push(result.data);

  renderComments();

  newComment.value = "";
}

async function initializePage() {

  currentResourceId =
    getResourceIdFromURL();

  if (!currentResourceId) {

    resourceTitle.textContent =
      "Resource not found.";

    return;
  }

  const [
    resourceResponse,
    commentsResponse
  ] = await Promise.all([

    fetch(`./api/index.php?id=${currentResourceId}`),

    fetch(
      `./api/index.php?resource_id=${currentResourceId}&action=comments`
    )
  ]);

  const resourceResult =
    await resourceResponse.json();

  const commentsResult =
    await commentsResponse.json();

  const resource =
    resourceResult.data;

  currentComments =
    commentsResult.data || [];

  if (resource) {

    renderResourceDetails(resource);

    renderComments();

    commentForm.addEventListener(
      "submit",
      handleAddComment
    );

  } else {

    resourceTitle.textContent =
      "Resource not found.";
  }
}

initializePage();
