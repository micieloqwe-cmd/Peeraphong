document.addEventListener("DOMContentLoaded", () => {
  let allProducts = [];
  let allCategories = [];

  // โหลดข้อมูลสินค้าและหมวดหมู่จาก database
  fetch("admin_get_products.php")
    .then(res => res.json())
    .then(data => {
      allProducts = data.products || [];
      allCategories = data.categories || [];
      renderProducts(allProducts);
      fillCategories(allCategories);
    });

  function renderProducts(products) {
    const container = document.getElementById("productList");
    container.innerHTML = "";
    if (!products.length) {
      container.innerHTML = "<div style='color:#888;text-align:center;'>ไม่พบสินค้า</div>";
      return;
    }
    products.forEach(p => {
      const lowStock = Number(p.stock) <= 5 ? " low" : "";
      const card = document.createElement("div");
      card.className = "product-card";
      card.innerHTML = `
        <span class="stock-badge${lowStock}">มีสต็อก (${p.stock})</span>
        <img src="../${p.image || 'uploads/noimage.png'}" alt="${p.product_name}">
        <h3>${p.product_name}</h3>
        <div class="brand"><b>ยี่ห้อ:</b> ${p.brand || "-"}</div>
        <div class="category"><b>หมวดหมู่:</b> ${getCategoryName(p.category_id)}</div>
        <div class="price">฿${Number(p.price).toLocaleString()}</div>
        <div class="actions">
          <button class="edit-btn" onclick="window.openProductModal('${encodeURIComponent(JSON.stringify(p))}')"><i class="fas fa-edit"></i> แก้ไข</button>
          <button class="delete-btn" onclick="deleteProduct(${p.product_id})"><i class="fas fa-trash"></i> ลบ</button>
        </div>
      `;
      container.appendChild(card);
    });
  }

  function fillCategories(categories) {
    const sel = document.getElementById("categorySelect");
    sel.innerHTML = `<option value="">ทุกประเภท</option>`;
    categories.forEach(c => {
      sel.innerHTML += `<option value="${c.category_id}">${c.category_name}</option>`;
    });
    // สำหรับ modal เพิ่ม/แก้ไข
    const modalSel = document.getElementById("modalCategory");
    if (modalSel) {
      modalSel.innerHTML = `<option value="">เลือกประเภท</option>`;
      categories.forEach(c => {
        modalSel.innerHTML += `<option value="${c.category_id}">${c.category_name}</option>`;
      });
    }
  }

  function getCategoryName(catId) {
    const cat = allCategories.find(c => String(c.category_id) === String(catId));
    return cat ? cat.category_name : "-";
  }

  // filter
  document.getElementById("searchInput").addEventListener("keyup", filterProducts);
  document.getElementById("categorySelect").addEventListener("change", filterProducts);

  function filterProducts() {
    const keyword = document.getElementById("searchInput").value.trim().toLowerCase();
    const catId = document.getElementById("categorySelect").value;
    let filtered = allProducts;
    if (catId) filtered = filtered.filter(p => String(p.category_id) === String(catId));
    if (keyword) {
      filtered = filtered.filter(p =>
        (p.product_name && p.product_name.toLowerCase().includes(keyword)) ||
        (p.brand && p.brand.toLowerCase().includes(keyword))
      );
    }
    renderProducts(filtered);
  }

  // เพิ่ม/แก้ไขสินค้า (modal)
  window.openProductModal = function (productStr) {
    let product = null;
    if (productStr) {
      try { product = JSON.parse(decodeURIComponent(productStr)); } catch { }
    }
    // ตรวจสอบทุก input ก่อนเซ็ตค่า ป้องกัน error
    const modalTitle = document.getElementById("modalTitle");
    const modalSaveText = document.getElementById("modalSaveText");
    const modalProductId = document.getElementById("modalProductId");
    const modalProductName = document.getElementById("modalProductName");
    const modalBrand = document.getElementById("modalBrand");
    const modalCategory = document.getElementById("modalCategory");
    const modalPrice = document.getElementById("modalPrice");
    const modalStock = document.getElementById("modalStock");
    const modalImage = document.getElementById("modalImage");
    const modalPreview = document.getElementById("modalPreview");
    const modalMsg = document.getElementById("modalMsg");
    const productModal = document.getElementById("productModal");
    const productForm = document.getElementById("productForm");

    if (modalTitle) modalTitle.textContent = product ? "แก้ไขสินค้า" : "เพิ่มสินค้า";
    if (modalSaveText) modalSaveText.textContent = product ? "บันทึกการแก้ไข" : "เพิ่มสินค้า";
    if (modalProductId) modalProductId.value = product ? product.product_id : "";
    if (modalProductName) modalProductName.value = product ? product.product_name : "";
    if (modalBrand) modalBrand.value = product ? product.brand : "";
    if (modalCategory) modalCategory.value = product ? product.category_id : "";
    if (modalPrice) modalPrice.value = product ? product.price : "";
    if (modalStock) modalStock.value = product ? product.stock : "";
    if (modalImage) modalImage.value = "";
    if (modalPreview) modalPreview.innerHTML = product && product.image ? `<img src="../${product.image}" style="max-width:120px;border-radius:10px;">` : "";
    if (modalMsg) modalMsg.textContent = "";
    if (productModal) productModal.style.display = "flex";
    if (productForm) productForm.setAttribute("data-edit", product && product.product_id ? product.product_id : "");
  };

  // ปิด modal
  document.getElementById("closeModalBtn").onclick = function () {
    document.getElementById("productModal").style.display = "none";
  };
  document.getElementById("cancelBtn").onclick = function () {
    document.getElementById("productModal").style.display = "none";
  };
  window.onclick = function (event) {
    if (event.target == document.getElementById("productModal")) {
      document.getElementById("productModal").style.display = "none";
    }
  };

  // Preview รูป
  document.getElementById("modalImage").onchange = function (e) {
    const file = e.target.files[0];
    const preview = document.getElementById("modalPreview");
    if (file && file.type.startsWith("image/")) {
      const reader = new FileReader();
      reader.onload = evt => preview.innerHTML = `<img src="${evt.target.result}" style="max-width:120px;border-radius:10px;">`;
      reader.readAsDataURL(file);
    } else {
      preview.innerHTML = "";
    }
  };

  // ส่งฟอร์มเพิ่ม/แก้ไขสินค้า
  document.getElementById("productForm").onsubmit = function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const editId = this.getAttribute("data-edit");
    if (editId) formData.append("product_id", editId);
    fetch(editId ? "admin_update_product.php" : "admin_add_product.php", {
      method: "POST",
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        document.getElementById("modalMsg").textContent = data.message;
        document.getElementById("modalMsg").style.color = data.success ? "green" : "red";
        if (data.success) {
          // ปิด popup modal เมื่อแก้ไขสินค้าสำเร็จ
          document.getElementById("productModal").style.display = "none";
          // รีเฟรชข้อมูลจาก database ทันที
          fetch("admin_get_products.php")
            .then(res => res.json())
            .then(data => {
              allProducts = data.products || [];
              allCategories = data.categories || [];
              renderProducts(allProducts);
              fillCategories(allCategories);
            });
        }
      });
  };

  // ลบสินค้า (แก้ให้ทำงานกับ PHP และฐานข้อมูล)
  window.deleteProduct = function (id) {
    const pid = Number(id);
    if (!Number.isInteger(pid) || pid <= 0) {
      alert("Product ID ไม่ถูกต้อง");
      return;
    }
    if (!confirm("คุณต้องการลบสินค้านี้หรือไม่?")) return;
    fetch("admin_delete_product.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: "id=" + encodeURIComponent(pid)
    })
    .then(async res => {
      const raw = await res.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch (e) {
        // ถ้าไม่ใช่ JSON ให้แสดง error ที่อ่านง่าย
        alert("❌ เซิร์ฟเวอร์ส่งข้อมูลผิดรูปแบบ\n\n" + raw);
        return;
      }
      alert(data.message);
      if (data.success) {
        fetch("admin_get_products.php")
          .then(res => res.json())
          .then(data => {
            allProducts = data.products || [];
            renderProducts(allProducts);
          });
      }
    })
    .catch(err => {
      console.error("Delete error:", err);
      alert("ลบไม่สำเร็จ: มีข้อผิดพลาดในการเชื่อมต่อ");
    });
  };

// ปุ่มเพิ่มสินค้า
document.getElementById("addProductBtn").onclick = function () {
  fetch("admin_get_products.php")
    .then(res => res.json())
    .then(data => {
      // กรองเฉพาะ category_id ที่เป็นตัวเลขและมีชื่อ
      allCategories = (data.categories || []).filter(c => Number(c.category_id) > 0 && c.category_name);
      fillCategories(allCategories);
      window.openProductModal();
    });
};
});
