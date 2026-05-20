let resources = [];

const resourceForm = document.querySelector("#resource-form");

const resourcesTbody = document.querySelector("#resources-tbody");

function createResourceRow(resource) {
 const tr = document.createElement("tr");

  tr.innerHTML = `
    <td>${resource.title}</td>
    <td>${resource.description}</td>
    <td>
      <a href="${resource.link}" target="_blank">
        ${resource.link}
      </a>
    </td>
    <td>
      <button class="edit-btn" data-id="${resource.id}">
        Edit
      </button>

      <button class="delete-btn" data-id="${resource.id}">
        Delete
      </button>
    </td>
  `;

  return tr;
}


function renderTable() {
  resourcesTbody.innerHTML = "";

  resources.forEach(resource => {

    const row = createResourceRow(resource);

    resourcesTbody.appendChild(row);
  });
}


async function handleAddResource(event) {
  event.preventDefault();

  const title = document.querySelector("#resource-title").value;

  const description = document.querySelector("#resource-description").value;

  const link = document.querySelector("#resource-link").value;

  const response = await fetch("./api/index.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      title,
      description,
      link
    })
  });

  const data = await response.json();

  resources.push({
    id: data.id,
    title,
    description,
    link
  });

  renderTable();

  resourceForm.reset();
}

function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {

    const id = event.target.dataset.id;

    await fetch(`./api/index.php?id=${id}`, {
      method: "DELETE"
    });

    resources = resources.filter(resource => resource.id != id);

    renderTable();
  }

  if (event.target.classList.contains("edit-btn")) {

    const id = event.target.dataset.id;

    const resource = resources.find(resource => resource.id == id);

    document.querySelector("#resource-title").value = resource.title;

    document.querySelector("#resource-description").value = resource.description;

    document.querySelector("#resource-link").value = resource.link;

    document.querySelector("#add-resource").textContent = "Update Resource";
  }}


async function loadAndInitialize() {
  const response = await fetch("./api/index.php");

  const result = await response.json();

  resources = result.data;

  renderTable();

  resourceForm.addEventListener("submit", handleAddResource);

  resourcesTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();