document.getElementById("adminLoginForm").onsubmit = function (e) {
  e.preventDefault();
  const formData = new FormData(this);

  fetch("login_admin.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      const msg = document.getElementById("adminLoginMsg");
      if (data.success) {
        // ดึงข้อมูลโปรไฟล์ admin
        fetch("get_admin_profile.php")
          .then((res) => res.json())
          .then((profile) => {
            if (profile.success) {
              sessionStorage.setItem("admin_firstname", profile.firstname || "");
              sessionStorage.setItem("admin_lastname", profile.lastname || "");
              sessionStorage.setItem("admin_email", profile.email || "");
            }
            // ✅ ไปหน้า Admin_index.html
            window.location.href = "../Admin_index/Admin_index.html";
          });
      } else {
        msg.textContent = data.message || "เข้าสู่ระบบไม่สำเร็จ";
      }
    });
};
