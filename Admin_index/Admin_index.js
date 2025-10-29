document.addEventListener("DOMContentLoaded", initDashboard);

async function initDashboard() {
  bindSidebarNav();

  const yearNow = new Date().getFullYear();
  const url = `admin_dashboard.php?year=${yearNow}&mode=complete_only`;

  let data;
  try {
    const res = await fetch(url, { cache: "no-store" });
    const raw = await res.text();
    try {
      data = JSON.parse(raw);
    } catch (e) {
      console.error("JSON parse error. RAW response:", raw);
      alert("❌ เซิร์ฟเวอร์ส่งข้อมูลไม่ถูกต้อง (ไม่ใช่ JSON)\n\nข้อมูลที่ได้รับ:\n" + raw);
      return;
    }
  } catch (e) {
    console.error("โหลดข้อมูลแดชบอร์ดล้มเหลว:", e);
    alert("โหลดข้อมูลแดชบอร์ดล้มเหลว");
    return;
  }
  if (!data?.ok) {
    alert("ไม่สามารถโหลดข้อมูลแดชบอร์ดได้");
    return;
  }

  // KPI
  setText("kpiTotalOrders", fmtNum(data.kpis.total_orders));
  setText("kpiCancelled",   fmtNum(data.kpis.orders.cancelled));
  setText("kpiChecking",    fmtNum(data.kpis.orders.checking));
  setText("kpiComplete",    fmtNum(data.kpis.orders.complete));
  setText("kpiPrepare",     fmtNum(data.kpis.orders.prepare)); // เตรียมส่งสินค้า
  setText("kpiRevenueAll",  money(data.kpis.revenue.all_time));
  setText("kpiRevenueMTD",  money(data.kpis.revenue.mtd));

  // Charts
  chartMonthlyRevenue(data.monthly_revenue);
  chartOrderStatus(data.kpis.orders);

  // Lists
  renderTopProducts(data.top_products);
  renderLowStock(data.low_stock);

  // แจ้งเตือนโหลดข้อมูลสำเร็จ
  const alertList = document.getElementById("alertList");
  if (alertList) {
    alertList.innerHTML = "<li>โหลดข้อมูลจากฐานข้อมูลเรียบร้อย</li>";
    if (data.kpis.total_orders === 0) {
      alertList.innerHTML += "<li>ยังไม่มีคำสั่งซื้อในระบบ</li>";
    }
    if (data.low_stock && data.low_stock.length > 0) {
      const low = data.low_stock.filter(p => p.stock <= 5);
      if (low.length > 0) {
        alertList.innerHTML += `<li style='color:#d92d20'>มีสินค้าคงเหลือน้อย: ${low.map(p=>`${esc(p.product_name)} (${fmtNum(p.stock)})`).join(", ")}</li>`;
      }
    }
  }
}

/* ====== Helpers ====== */
function bindSidebarNav() {
  const go = (id, href) => {
    const el = document.getElementById(id);
    if (el) el.onclick = () => (location.href = href);
  };
  go("dashboardMenu", "../Admin_index/Admin_index.html");
  go("productMenu", "../admin_products/admin_products.html");
  go("paymentMenu", "../Admin_Payment/Admin_Payment.html");
  go("reportMenu",  "../Admin_report/Admin_report.html");
  go("profileMenu", "../Admin_personal information/personal information.html");
}
function setText(id, val){ const el = document.getElementById(id); if(el) el.textContent = val; }
const fmtNum = n => (+n||0).toLocaleString("th-TH");
const money  = n => "฿"+(+n||0).toLocaleString("th-TH",{maximumFractionDigits:2});
const esc = s => String(s??'').replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));

/* ====== Charts ====== */
function chartMonthlyRevenue(m) {
  const el = document.getElementById("chartMonthlyRevenue");
  if (!el) return;
  const monthsTH = ["ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
  const series = Array.from({length:12},(_,i)=>{
    const r = m.rows.find(x => +x.month === i+1);
    return r ? +r.total : 0;
  });
  new Chart(el, {
    type: "bar",
    data: { labels: monthsTH, datasets: [{ label: `ยอดรวมออเดอร์ปี ${m.year} (บาท)`, data: series }] },
    options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
  });
}
function chartOrderStatus(o) {
  const el = document.getElementById("chartOrderStatus");
  if (!el) return;
  const labels = ["ยกเลิกคำสั่งจากระบบ","รอตรวจสอบ","ชำระเงินสมบูรณ์","เตรียมส่งสินค้าให้คุณแล้ว"];
  const values = [o.cancelled, o.checking, o.complete, o.prepare];
  new Chart(el, {
    type: "doughnut",
    data: { labels, datasets: [{ data: values }] },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
  });
}

/* ====== Lists ====== */
function renderTopProducts(rows){
  const ul = document.getElementById("topProductsList");
  if (!ul) return;
  ul.innerHTML = "";
  if (!rows?.length){ ul.innerHTML = "<li>ยังไม่มีข้อมูล</li>"; return; }
  const maxQty = Math.max(...rows.map(r=>+r.qty_sold||0),1);
  rows.forEach((p,i)=>{
    const pct = Math.round((+p.qty_sold||0)*100/maxQty);
    const li = document.createElement("li");
    li.innerHTML = `
      <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin:6px 0;">
        <div><strong>#${i+1}</strong> ${esc(p.product_name)}</div>
        <div class="ok">${fmtNum(p.qty_sold)} ชิ้น • ${money(p.revenue)}</div>
      </div>
      <div class="progress"><div style="width:${pct}%"></div></div>`;
    ul.appendChild(li);
  });
}
function renderLowStock(rows){
  const ul = document.getElementById("lowStockList");
  if (!ul) return;
  ul.innerHTML = "";
  if (!rows?.length){ ul.innerHTML = "<li>ไม่พบคอลัมน์สต็อก หรือยังไม่มีข้อมูล</li>"; return; }
  rows.forEach(p=>{
    const li = document.createElement("li");
    li.innerHTML = `<div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin:6px 0;">
        <div><strong>${esc(p.product_name)}</strong></div>
        <div>คงเหลือ: <b>${fmtNum(p.stock)}</b> ชิ้น</div>
      </div>`;
    ul.appendChild(li);
  });
}
