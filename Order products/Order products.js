document.addEventListener("DOMContentLoaded", async () => {
  /* ===========================
     ดึงข้อมูลตะกร้าจาก localStorage
  =========================== */
  const cart = JSON.parse(localStorage.getItem("cart") || "[]");
  let total = 0;
  let html = `<table style="width:100%;border-collapse:collapse;border-radius:15px;overflow:hidden;box-shadow:0 8px 25px rgba(0,0,0,0.1);">
    <tr style="background:linear-gradient(135deg, #0d47a1, #1565c0);color:#fff;">
      <th style="padding:16px;">สินค้า</th>
      <th style="padding:16px;">จำนวน</th>
      <th style="padding:16px;">ราคา</th>
      <th style="padding:16px;">รวม</th>
    </tr>`;

  cart.forEach((item, index) => {
    const sum = item.price * item.qty;
    total += sum;
    html += `<tr style="background:${index % 2 === 0 ? "#f8f9ff" : "#ffffff"};">
      <td style="padding:16px;">${item.name}</td>
      <td style="padding:16px;">${item.qty}</td>
      <td style="padding:16px;">${Number(item.price).toLocaleString()} บาท</td>
      <td style="padding:16px;">${sum.toLocaleString()} บาท</td>
    </tr>`;
  });

  html += `<tr style="font-weight:bold;background:linear-gradient(135deg, #e3f2fd, #f1f5ff);">
    <td colspan="3" style="text-align:right;padding:16px;font-size:18px;">รวมทั้งหมด</td>
    <td style="padding:16px;font-size:18px;color:#0d47a1;">${total.toLocaleString()} บาท</td>
  </tr></table>`;

  document.getElementById("orderList").innerHTML = html;
  document.getElementById("totalAmount").textContent = total;
  document.getElementById("totalAmount").dataset.value = total;

  /* ===========================
     ดึงข้อมูลโปรไฟล์ผู้ใช้ที่ล็อกอิน
  =========================== */
  try {
    const res = await fetch(API_CHECK_LOGIN, {
      credentials: "include"
    });
    if (!res.ok) throw new Error("Network response was not ok");
    const user = await res.json();

    if (!user.loggedIn) {
      alert("กรุณาเข้าสู่ระบบก่อนทำรายการ");
      window.location.href = "../Login/Login.html";
      return;
    }

    // ✅ แสดงชื่อและอีเมลบนหน้าเว็บ (ถ้ามี element)
    const fullnameEl = document.getElementById("userFullname");
    const emailEl = document.getElementById("userEmail");

    if (fullnameEl) fullnameEl.textContent = `${user.firstname} ${user.lastname}`;
    if (emailEl) emailEl.textContent = user.email;

    // ✅ เก็บข้อมูลไว้ใน localStorage เพื่อใช้ส่งตอนแจ้งโอนเงิน
    localStorage.setItem("userEmail", user.email);
    localStorage.setItem("userName", `${user.firstname} ${user.lastname}`);

    console.log("ผู้ใช้ที่ล็อกอิน:", user);

  } catch (err) {
    console.error("❌ โหลดโปรไฟล์ไม่สำเร็จ:", err);
    alert("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
  }

  /* ===========================
     ดึงเบอร์บัญชีร้าน + QR PromptPay
  =========================== */
  fetch(API_ORDER)
    .then(res => res.text())
    .then(raw => {
      try {
        const data = JSON.parse(raw);
        const phone = data.phone || "0964014011";
        document.getElementById("bankAccount").textContent = phone;
        document.getElementById("qrImage").src = `https://www.pp-qr.com/api/image/${phone}/${total}`;
      } catch (e) {
        console.warn("Order.php returned non-JSON (phone):", raw);
        document.getElementById("qrImage").src = `https://www.pp-qr.com/api/image/0964014011/${total}`;
      }
    })
    .catch(err => {
      console.error("Fetch error for phone:", err);
      document.getElementById("qrImage").src = `https://www.pp-qr.com/api/image/0964014011/${total}`;
    });

  /* ===========================
     จัดการสถานะและนับเวลา (server-driven countdown)
  =========================== */
  let timer = null;
  const countdownEl = document.getElementById("countdown");
  const statusEl = document.getElementById("orderStatus");
  const uploadBox = document.getElementById("uploadBox");
  const payBtn = document.getElementById("payBtn");
  const cancelBtn = document.getElementById("cancelBtn");
  const receiptImg = document.getElementById("receiptImg");
  const previewImg = document.getElementById("previewImg");

  function setStatus(text) {
    if (statusEl) statusEl.textContent = text;
  }

  // เริ่ม polling จาก server เพื่อดึงเวลาที่เหลือ (ต้องมี order_number ใน localStorage)
  async function startCountdownFromServer() {
    const order_number = localStorage.getItem("order_number");
    if (!order_number) {
      // ถ้าไม่มี order_number ให้แสดงข้อความและปิดการจ่ายเงิน
      setStatus("ไม่มีหมายเลขคำสั่งซื้อ กรุณากลับไปสั่งซื้อใหม่");
      uploadBox.style.display = "none";
      payBtn.disabled = true;
      cancelBtn.disabled = true;
      return;
    }

    async function update() {
      try {
        const res = await fetch(API_ORDER, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "get_timer", order_number }),
        });
        const raw = await res.text();
        if (!res.ok) {
          console.error("get_timer HTTP error:", res.status, raw);
          if (countdownEl) countdownEl.textContent = "⏰ โหลดเวลานับถอยหลังไม่สำเร็จ";
          return;
        }
        let data;
        try {
          data = JSON.parse(raw);
        } catch (e) {
          console.error("get_timer returned non-JSON:", raw);
          if (countdownEl) countdownEl.textContent = "⏰ โหลดเวลานับถอยหลังไม่สำเร็จ";
          return;
        }

        if (data.status === "counting" && typeof data.seconds_left === "number") {
          const left = data.seconds_left;
          const min = Math.floor(left / 60);
          const sec = left % 60;
          if (countdownEl) countdownEl.textContent = `⏰ เวลาที่เหลือ: ${min}:${sec.toString().padStart(2,"0")}`;
          setStatus("ยืนยันการสั่งซื้อและรอชำระเงิน");
          // keep UI enabled
          uploadBox.style.display = "";
          payBtn.disabled = false;
          cancelBtn.disabled = false;
        } else if (data.status === "expired") {
          if (countdownEl) countdownEl.textContent = "หมดเวลา กรุณาทำรายการใหม่";
          setStatus("ยกเลิกคำสั่งซื้อโดยระบบ (หมดเวลา)");
          // cleanup local data and disable UI
          localStorage.removeItem("cart");
          localStorage.removeItem("order_number");
          localStorage.removeItem("expire_at");
          uploadBox.style.display = "none";
          payBtn.disabled = true;
          receiptImg.disabled = true;
          cancelBtn.disabled = true;
          clearInterval(timer);
        } else {
          // error
          console.warn("get_timer response:", data);
          if (countdownEl) countdownEl.textContent = "⏰ โหลดเวลานับถอยหลังไม่สำเร็จ";
        }
      } catch (err) {
        console.error("Error fetching get_timer:", err);
        if (countdownEl) countdownEl.textContent = "⏰ โหลดเวลานับถอยหลังไม่สำเร็จ";
      }
    }

    await update();
    timer = setInterval(update, 1000);
  }

  // เรียกใช้งานครั้งแรก
  startCountdownFromServer();

  /* ===========================
     ปุ่ม "แจ้งโอนเงิน" (แก้ใหม่)
  =========================== */
  payBtn.addEventListener("click", () => {
    if (!receiptImg.files[0]) {
      alert("⚠️ กรุณาอัปโหลดใบเสร็จการโอนเงินก่อน");
      return;
    }

    setStatus("✅ ชำระเงินสำเร็จรอการตรวจสอบ");
    payBtn.disabled = true;
    receiptImg.disabled = true;
    clearInterval(timer);
    countdownEl.textContent = "";

    // เตรียม items ให้รองรับ payment_items (product_id + qty + price)
    const items = (JSON.parse(localStorage.getItem("cart") || "[]")).map(it => ({
      product_id: it.product_id || it.id || null,
      product_name: it.name || "",
      quantity: Number(it.qty || 1),
      price: Number(it.price || 0)
    }));

    const reader = new FileReader();
    reader.onload = function (evt) {
      fetch(API_ORDER, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "notify_payment",
          order_id: Number(localStorage.getItem("order_id")) || 0,
          order_number: localStorage.getItem("order_number") || ("ORD-" + Date.now()),
          amount: total,
          payment_method: "bank_transfer",
          bank_account: document.getElementById("bankAccount").textContent,
          slip_image: evt.target.result,
          email: localStorage.getItem("userEmail"),
          fullname: localStorage.getItem("userName"),
          items
        }),
      })
        .then(async (res) => {
           const raw = await res.text();
           if (!res.ok) {
             console.error("HTTP Error:", res.status, raw);
             alert("❌ เซิร์ฟเวอร์ผิดพลาด (" + res.status + ")");
             return;
           }
           let data;
           try {
             if (!raw.trim().startsWith("{") && !raw.trim().startsWith("[")) {
               throw new Error("Response is not JSON");
             }
             data = JSON.parse(raw);
           } catch (e) {
             console.error("JSON parse error. RAW response:", raw);
             alert("❌ เซิร์ฟเวอร์ส่งข้อมูลไม่ถูกต้อง (ไม่ใช่ JSON)\n\nข้อมูลที่ได้รับ:\n" + raw);
             return;
           }

          if (data.status === "success") {
            alert(
              `✅ แจ้งชำระเงินสำเร็จ!\n\nรหัสชำระ: ${data.payment_code}\nคำสั่งซื้อ: ${data.order_number}\nผู้สั่ง: ${data.fullname}\nอีเมล: ${data.email}`
            );
            if (window.showPaymentInfo) {
            }
            localStorage.removeItem("cart");
          } else if (data.message === "ไม่พบหมายเลขคำสั่งซื้อในระบบ") {
            alert("❌ ไม่พบหมายเลขคำสั่งซื้อในระบบ\nกรุณากลับไปหน้าตะกร้าและกดสั่งซื้อใหม่อีกครั้ง");
            // ลบข้อมูล order ที่อาจค้างอยู่
            localStorage.removeItem("order_number");
            localStorage.removeItem("order_id");
            localStorage.removeItem("cart");
            // redirect ไปหน้าตะกร้า
            window.location.href = "../Product/basket.html";
            return;
          } else {
            alert("❌ " + (data.message || "ไม่สามารถบันทึกข้อมูลได้"));
          }
        })
        .catch((err) => {
          console.error("Fetch error:", err);
          alert("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
        });
    };
    reader.readAsDataURL(receiptImg.files[0]);
  });


  /* ===========================
     ปุ่ม "ยกเลิกคำสั่งซื้อ"
  =========================== */
  cancelBtn.addEventListener("click", () => {
    if (confirm("คุณแน่ใจหรือไม่ที่จะยกเลิกคำสั่งซื้อ?")) {
      setStatus("❌ ยกเลิกคำสั่งซื้อโดยผู้ซื้อ");
      const items = (JSON.parse(localStorage.getItem("cart") || "[]")).map(it => ({
        product_id: it.product_id || it.id || null,
        product_name: it.name || "",
        quantity: Number(it.qty || 1),
        price: Number(it.price || 0)
      }));
      localStorage.removeItem("cart");
      fetch("Order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "cancel_order", items }),
      }).catch(() => { });
      clearInterval(timer);
      countdownEl.textContent = "";
      payBtn.disabled = true;
      receiptImg.disabled = true;
      cancelBtn.disabled = true;
    }
  });

  /* ===========================
     พรีวิวใบเสร็จ
  =========================== */
  receiptImg.addEventListener("change", (e) => {
    const file = e.target.files[0];
    previewImg.innerHTML = "";
    if (file && file.type.startsWith("image/")) {
      const reader = new FileReader();
      reader.onload = (evt) => {
        previewImg.innerHTML = `<img src="${evt.target.result}" alt="ใบเสร็จ" style="max-width:280px;max-height:280px;border-radius:20px;border:3px solid #0d47a1;box-shadow:0 10px 25px rgba(0,0,0,0.2);">`;
      };
      reader.readAsDataURL(file);
    }
  });
});

// เพิ่มตัวแปรกำหนด endpoint ชัดเจน (no spaces)
const API_ORDER = "./Order.php";
const API_CHECK_LOGIN = "./check_login.php";
