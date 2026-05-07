let currentTopicId = null;
let currentReplies = [];

const topicSubject =
  document.querySelector("#topic-subject");

const opMessage =
  document.querySelector("#op-message");

const opFooter =
  document.querySelector("#op-footer");

const replyListContainer =
  document.querySelector("#reply-list-container");

const replyForm =
  document.querySelector("#reply-form");

const newReplyText =
  document.querySelector("#new-reply");

function getTopicIdFromURL() {

  const queryString =
    window.location.search;

  const params =
    new URLSearchParams(queryString);

  return params.get("id");
}

function renderOriginalPost(topic) {

  topicSubject.textContent =
    topic.subject;

  opMessage.textContent =
    topic.message;

  opFooter.textContent =
    `Posted by: ${topic.author} on ${topic.created_at}`;
}

function createReplyArticle(reply) {

  const article =
    document.createElement("article");

  article.innerHTML = `
    <p>${reply.text}</p>

    <footer>
      Posted by: ${reply.author}
      on ${reply.created_at}
    </footer>

    <div>
      <button
        class="delete-reply-btn"
        data-id="${reply.id}"
      >
        Delete
      </button>
    </div>
  `;

  return article;
}

function renderReplies() {

  replyListContainer.innerHTML = "";

  currentReplies.forEach(reply => {

    const article =
      createReplyArticle(reply);

    replyListContainer.appendChild(article);
  });
}

async function handleAddReply(event) {

  event.preventDefault();

  const replyText =
    newReplyText.value.trim();

  if (!replyText) {
    return;
  }

  const response =
    await fetch("./api/index.php?action=reply", {

      method: "POST",

      headers: {
        "Content-Type": "application/json"
      },

      body: JSON.stringify({
        topic_id: currentTopicId,
        author: "Student",
        text: replyText
      })
    });

  const result =
    await response.json();

  if (result.success) {

    currentReplies.push(result.data);

    renderReplies();

    newReplyText.value = "";
  }
}

async function handleReplyListClick(event) {

  if (
    event.target.classList.contains(
      "delete-reply-btn"
    )
  ) {

    const id =
      event.target.dataset.id;

    await fetch(
      `./api/index.php?action=delete_reply&id=${id}`,
      {
        method: "DELETE"
      }
    );

    currentReplies =
      currentReplies.filter(
        reply => reply.id != id
      );

    renderReplies();
  }
}

async function initializePage() {

  currentTopicId =
    getTopicIdFromURL();

  if (!currentTopicId) {

    topicSubject.textContent =
      "Topic not found.";

    return;
  }

  const [
    topicResponse,
    repliesResponse
  ] = await Promise.all([

    fetch(`./api/index.php?id=${currentTopicId}`),

    fetch(
      `./api/index.php?action=replies&topic_id=${currentTopicId}`
    )
  ]);

  const topicResult =
    await topicResponse.json();

  const repliesResult =
    await repliesResponse.json();

  const topic =
    topicResult.data;

  currentReplies =
    repliesResult.data || [];

  if (topic) {

    renderOriginalPost(topic);

    renderReplies();

    replyForm.addEventListener(
      "submit",
      handleAddReply
    );

    replyListContainer.addEventListener(
      "click",
      handleReplyListClick
    );

  } else {

    topicSubject.textContent =
      "Topic not found.";
  }
}

initializePage();
