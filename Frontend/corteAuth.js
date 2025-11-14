// corteAuth.js - handles user storage

const CorteAuth = {
  getUsers: function () {
    return JSON.parse(localStorage.getItem("users") || "{}");
  },

  saveUsers: function (users) {
    localStorage.setItem("users", JSON.stringify(users));
  }
};
