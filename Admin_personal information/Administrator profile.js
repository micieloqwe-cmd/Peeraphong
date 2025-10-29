document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("adminProfileForm");
  const firstName = document.getElementById("adminFirstName");
  const lastName = document.getElementById("adminLastName");
  const email = document.getElementById("adminEmail");
  const buttonsDiv = document.getElementById("profileButtons");
  const msg = document.getElementById("profileMsg");

  // เก็บค่าเดิม
  let originalData = {
    firstname: firstName.value,
    lastname: lastName.value,
    email: email.value,
  };

  // ฟังก์ชันเปิดโหมดแก้ไข
  function enableEditMode() {
    firstName.disabled = false;
    lastName.disabled = false;
    email.disabled = false;

    buttonsDiv.innerHTML = `
      <button type="submit" class="btn save"><i class="fas fa-save"></i> บันทึก</button>
      <button type="button" id="cancelBtn" class="btn password"><i class="fas fa-times"></i> ยกเลิก</button>
    `;

    // ปุ่มยกเลิก
    document.getElementById("cancelBtn").onclick = function () {
      firstName.value = originalData.firstname;
      lastName.value = originalData.lastname;
      email.value = originalData.email;

      firstName.disabled = true;
      lastName.disabled = true;
      email.disabled = true;

      resetToEditButton();
    };
  }

  // ฟังก์ชันเปลี่ยนกลับเป็นปุ่ม "แก้ไขข้อมูล"
  function resetToEditButton() {
    buttonsDiv.innerHTML = `
      <button type="button" id="editBtn" class="btn save">
        <i class="fas fa-edit"></i> แก้ไขข้อมูล
      </button>
    `;
    document.getElementById("editBtn").onclick = enableEditMode;
  }

  // Submit form
  form.onsubmit = function (e) {
    e.preventDefault();
    const formData = new FormData(form);
    fetch("update_admin_profile.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        msg.textContent = data.message;
        msg.style.color = data.success ? "green" : "red";

        // ✅ ทำให้ข้อความหายไปใน 3 วิ
        setTimeout(() => {
          msg.textContent = "";
        }, 3000);

        if (data.success) {
          // อัปเดตค่าเดิม
          originalData = {
            firstname: firstName.value,
            lastname: lastName.value,
            email: email.value,
          };

          firstName.disabled = true;
          lastName.disabled = true;
          email.disabled = true;

          resetToEditButton();
        }
      })
      .catch(() => {
        msg.textContent = "เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์";
        msg.style.color = "red";
        setTimeout(() => {
          msg.textContent = "";
        }, 3000);
      });
  };

  // เริ่มต้นด้วยปุ่มแก้ไข
  resetToEditButton();
});
