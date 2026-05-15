const listContainer = document.getElementById("assignments-list");

async function loadAssignments() {
  const response = await fetch("./api/index.php");
  const result = await response.json();
  if (result.success) {
    listContainer.innerHTML = "";
    result.data.forEach(a => {
      const div = document.createElement("div");
      div.className = "assignment-item";
      div.innerHTML = `
        <h3>${a.title}</h3>
        <p>Due: ${a.due_date}</p>
        <a href="details.html?id=${a.id}">View Details</a>
      `;
      listContainer.appendChild(div);
    });
  }
}

loadAssignments();
