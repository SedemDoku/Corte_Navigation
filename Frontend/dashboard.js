
document.getElementById("searchBtn").addEventListener("click", () => {
  const start = document.getElementById("start").value.trim();
  const end = document.getElementById("end").value.trim();
  const error = document.getElementById("errorMsg");
  const mapFrame = document.getElementById("mapFrame");

  if (!start || !end) {
    error.textContent = "Please enter both start and destination.";
    return;
  }

  error.textContent = "";

  const query = `${start} to ${end}, Accra`;
  const url = `https://www.google.com/maps?q=${encodeURIComponent(query)}&output=embed`;

  mapFrame.src = url;
});

const logoutBtn = document.getElementById("logoutBtn");
if (logoutBtn) {
  logoutBtn.addEventListener("click", (e) => {
    e.preventDefault();
    CorteAuth.logout();
  });
}
