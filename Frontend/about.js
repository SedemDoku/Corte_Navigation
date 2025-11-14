// about.js
// No need to repeat auth checks here because auth.js runs on DOMContentLoaded
// but keep small file to allow future page-specific behavior.

const logoutBtn = document.getElementById("logoutBtn");
if (logoutBtn) {
  logoutBtn.addEventListener("click", (e) => {
    e.preventDefault();
    CorteAuth.logout();
  });
}
