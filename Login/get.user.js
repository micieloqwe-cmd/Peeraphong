function goToProducts() {
  window.location.href = "index.html";
}

function goToLogin() {
  window.location.href = "../Login/login.html";
}
function basket() {
  window.location.href = "order-history.html";
}
function goToRegister() {
  window.location.href = "../Register/apply.html";
}
fetch('get_user.php')
      .then(res => res.json())
      .then(data => {
        document.getElementById('userEmail').textContent = data.email;
      });

    // ฟังก์ชันตะกร้า
    function basket() {
      alert("เปิดตะกร้าสินค้า (ฟังก์ชันตัวอย่าง)");
    }

    // ฟังก์ชันเพิ่มสินค้า
    document.querySelectorAll('.add-to-cart').forEach((btn, index) => {
      btn.addEventListener('click', () => {
        const productCard = btn.closest('.product-card');
        const name = productCard.querySelector('h4').textContent;
        const price = productCard.querySelector('p').textContent.replace(' บาท', '').replace(',', '');
        alert(`เพิ่ม ${name} ราคา ${price} บาท ลงในตะกร้าแล้ว`);
        // ต่อยอด: ส่งข้อมูลไป backend ผ่าน AJAX
      });
    });

    // ensure email is lowercased before login form submit
    document.addEventListener('DOMContentLoaded', () => {
      const loginForm = document.getElementById('loginForm') || document.querySelector('form[action="login.php"]');
      if (loginForm) {
        const emailInput = loginForm.querySelector('#email');
        if (emailInput) {
          loginForm.addEventListener('submit', () => {
            emailInput.value = emailInput.value.trim().toLowerCase();
          });
        }
      }
    });
