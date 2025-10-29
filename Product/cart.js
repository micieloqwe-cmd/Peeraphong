document.getElementById('orderBtn').onclick = async function () {
  const cart = JSON.parse(localStorage.getItem("cart") || "[]");
  if (cart.length === 0) {
    alert("ไม่มีสินค้าในตะกร้า");
    return;
  }

  const userEmail = document.getElementById("userEmail").textContent || "";
  const fullname = document.getElementById("userFullname").textContent.split(" ");
  const firstname = fullname[0] || "";
  const lastname = fullname[1] || "";
  const totalAmount = cart.reduce((sum, item) => sum + (Number(item.price || 0) * Number(item.qty || 1)), 0);

  try {
    // 1) สร้างคำสั่งซื้อ (create_order)
    const resCreate = await fetch("../Order products/Order.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "create_order", total: totalAmount })
    });
    const createData = await resCreate.json();
    if (!createData.order_number) {
      alert("ไม่สามารถสร้างคำสั่งซื้อได้");
      return;
    }
    const order_number = createData.order_number;
    const expire_at = createData.expire_at || null;
    localStorage.setItem("order_number", order_number);
    if (expire_at) localStorage.setItem("expire_at", expire_at);

    // 2) สำรอง/ตัดสต๊อก
    const resReserve = await fetch("../Order products/Order.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "reserve_stock", items: cart })
    });
    const reserveData = await resReserve.json();
    if (reserveData.status !== "success") {
      // ยกเลิก order ถ้าจำเป็น
      await fetch("../Order products/Order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "cancel_by_system", order_number, items: cart })
      }).catch(()=>{});
      alert("ตัดสต็อกไม่สำเร็จ: " + (reserveData.message || ""));
      return;
    }

    // เก็บข้อมูลสำหรับหน้าต่อไป
    localStorage.setItem("order_items", JSON.stringify(cart));
    localStorage.setItem("userEmail", userEmail);
    localStorage.setItem("userName", `${firstname} ${lastname}`.trim());

    alert("✅ ทำรายการสั่งซื้อสำเร็จ");
    localStorage.removeItem("cart"); // ถ้าต้องการ เคลียร์ที่นี่ หรือให้หน้า Order products แสดงก่อนล้าง
    window.location.href = "../Order products/Order products.html";
  } catch (err) {
    console.error(err);
    alert("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
  }
};
