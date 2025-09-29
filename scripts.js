function checkpassword() {
  const password = document.getElementById("password").value;
  const rePassword = document.getElementById("re-password").value;

  if (password !== rePassword) {
    alert("Passwords do not match!");
    return false;
  } else {
    return true;
  }
}
function redirectToRolePage() {
  const role = document.getElementById("role").value;
  if (role === "student") {
    window.location.href = "student.php";
  } else if (role === "Faculty") {
    window.location.href = "teacher.php";
  } else {
    alert("Please select a role before continuing.");
  }
}
