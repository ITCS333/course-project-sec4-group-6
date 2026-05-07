let topics = [];

const newTopicForm =
  document.querySelector("#new-topic-form");

const topicListContainer =
  document.querySelector("#topic-list-container");

function createTopicArticle(topic) {

  const article =
    document.createElement("article");

  article.innerHTML = `
    <h3>
      <a href="topic.html?id=${topic.id}">
        ${topic.subject}
      </a>
    </h3>

    <footer>
      Posted by: ${topic.author}
      on ${topic.created_at}
    </footer>

    <div>

      <button
        class="edit-btn"
        data-id="${topic.id}"
      >
        Edit
      </button>

      <button
        class="delete-btn"
        data-id="${topic.id}"
      >
        Delete
      </button>

    </div>
  `;

  return article;
}

function renderTopics() {

  topicListContainer.innerHTML = "";

  topics.forEach(topic => {

    const article =
      createTopicArticle(topic);

    topicListContainer.appendChild(article);
  });
}

async function handleCreateTopic(event) {

  event.preventDefault();

  const subject =
    document.querySelector("#topic-subject").value;

  const message =
    document.querySelector("#topic-message").value;

  const submitButton =
    document.querySelector("#create-topic");

  const editId =
    submitButton.dataset.editId;

  if (editId) {

    await handleUpdateTopic(editId, {
      subject,
      message
    });

    submitButton.textContent =
      "Create Topic";

    delete submitButton.dataset.editId;

  } else {

    const response =
      await fetch("./api/index.php", {

        method: "POST",

        headers: {
          "Content-Type": "application/json"
        },

        body: JSON.stringify({
          subject,
          message,
          author: "Student"
        })
      });

    const result =
      await response.json();

    if (result.success) {

      topics.push({
        id: result.id,
        subject,
        message,
        author: "Student",
        created_at: new Date()
          .toISOString()
          .slice(0, 19)
          .replace("T", " ")
      });

      renderTopics();

      newTopicForm.reset();
    }
  }
}

async function handleUpdateTopic(id, fields) {

  await fetch("./api/index.php", {

    method: "PUT",

    headers: {
      "Content-Type": "application/json"
    },

    body: JSON.stringify({
      id,
      subject: fields.subject,
      message: fields.message
    })
  });

  topics = topics.map(topic => {

    if (topic.id == id) {

      return {
        ...topic,
        subject: fields.subject,
        message: fields.message
      };
    }

    return topic;
  });

  renderTopics();

  newTopicForm.reset();
}

async function handleTopicListClick(event) {

  if (event.target.classList.contains("delete-btn")) {

    const id =
      event.target.dataset.id;

    await fetch(`./api/index.php?id=${id}`, {
      method: "DELETE"
    });

    topics =
      topics.filter(topic => topic.id != id);

    renderTopics();
  }

  if (event.target.classList.contains("edit-btn")) {

    const id =
      event.target.dataset.id;

    const topic =
      topics.find(topic => topic.id == id);

    document.querySelector("#topic-subject").value =
      topic.subject;

    document.querySelector("#topic-message").value =
      topic.message;

    const submitButton =
      document.querySelector("#create-topic");

    submitButton.textContent =
      "Update Topic";

    submitButton.dataset.editId = id;
  }
}

async function loadAndInitialize() {

  const response =
    await fetch("./api/index.php");

  const result =
    await response.json();

  topics = result.data;

  renderTopics();

  newTopicForm.addEventListener(
    "submit",
    handleCreateTopic
  );

  topicListContainer.addEventListener(
    "click",
    handleTopicListClick
  );
}

loadAndInitialize();
