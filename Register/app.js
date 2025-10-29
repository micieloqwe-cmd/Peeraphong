function prevStep() {
  window.location.href = "order-history.html";
}
// แสดง Step ถัดไป
function nextStep(step) {
  // ซ่อนทุก Step ก่อน
  document.querySelectorAll(".step-box").forEach(box => box.classList.add("hidden"));
  // แสดง Step ที่เลือก
  document.getElementById("step" + step).classList.remove("hidden");

  // อัพเดท Indicator
  updateIndicator(step);
}

// ย้อนกลับ Step
function prevStep(step) {
  document.querySelectorAll(".step-box").forEach(box => box.classList.add("hidden"));
  document.getElementById("step" + step).classList.remove("hidden");

  updateIndicator(step);
}

// อัพเดท Indicator (เปลี่ยนสี step)
function updateIndicator(step) {
  const steps = document.querySelectorAll(".step");
  steps.forEach((el, i) => {
    if (i < step) {
      el.classList.add("active");
    } else {
      el.classList.remove("active");
    }
  });
}

// ตรวจสอบฟอร์มก่อน submit
function validateForm() {
  const pass = document.getElementById("password").value;
  const confirm = document.getElementById("confirm_password").value;

  if (pass !== confirm) {
    alert("❌ รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน");
    return false;
  }
  return true;
}

function nextStep(step) {
  // Step 1: ตรวจสอบ Email & Password
  if (step === 2) {
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value.trim();
    const confirm = document.getElementById("confirm_password").value.trim();

    if (!email.includes("@")) {
      alert("กรุณากรอกอีเมลให้ถูกต้อง (ต้องมี @)");
      return false;
    }

    const passwordPattern = /^(?=.*[a-zA-Z])(?=.*\d).{6,}$/;
    if (!passwordPattern.test(password)) {
      alert("รหัสผ่านต้องมี:\n- ตัวอักษร (a-z, A-Z)\n- ตัวเลข (0-9)\nและยาวอย่างน้อย 6 ตัวอักษร");
      return false;
    }

    if (password !== confirm) {
      alert("รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน");
      return false;
    }
  }

  // Step 2: ตรวจสอบชื่อ-นามสกุล
  if (step === 3) {
    const firstname = document.getElementById("firstname").value.trim();
    const lastname = document.getElementById("lastname").value.trim();

    if (firstname === "" || lastname === "") {
      alert("กรุณากรอกชื่อและนามสกุลให้ครบถ้วน");
      return false;
    }
  }

  // แสดง Step ที่เลือก
  document.querySelectorAll('.step-box').forEach(s => s.classList.add('hidden'));
  document.getElementById('step' + step).classList.remove('hidden');

  // อัพเดทวงกลม Step
  document.querySelectorAll('.step').forEach((el, idx) => {
    el.classList.toggle('active', idx < step);
  });
}

function prevStep(step) {
  nextStep(step);
}

// ตรวจสอบ Step 3 ก่อน Submit
function validateForm() {
  const address = document.querySelector('input[name="address"]').value.trim();
  const street = document.querySelector('input[name="street"]').value.trim();
  const soi = document.querySelector('input[name="soi"]').value.trim();
  const district = document.querySelector('input[name="district"]').value.trim();
  const province = document.getElementById('province').value;
  const amphoe = document.getElementById('amphoe').value;
  const phone = document.getElementById('phone').value.trim();

  // บ้านเลขที่
  if (address === "") {
    alert("กรุณากรอกบ้านเลขที่");
    return false;
  }
  if (!/^[0-9]+$/.test(address)) {
    alert("บ้านเลขที่ต้องเป็นตัวเลขเท่านั้น");
    return false;
  }

  // ถนน, ซอย, ตำบล
  if (street === "") {
    alert("กรุณากรอกถนน");
    return false;
  }
  if (soi === "") {
    alert("กรุณากรอกซอย");
    return false;
  }
  if (district === "") {
    alert("กรุณากรอกตำบล");
    return false;
  }

  // จังหวัดและอำเภอ
  if (province === "") {
    alert("กรุณาเลือกจังหวัด");
    return false;
  }
  if (amphoe === "") {
    alert("กรุณาเลือกอำเภอ");
    return false;
  }

  // เบอร์โทรศัพท์
  if (phone === "") {
    alert("กรุณากรอกเบอร์โทรศัพท์");
    return false;
  }
  if (!/^0[0-9]{9}$/.test(phone)) {
    alert("เบอร์โทรศัพท์ต้องขึ้นต้นด้วย 0 และมี 10 หลัก");
    return false;
  }
  // แสดง Step ที่เลือก
  document.querySelectorAll('.step-box').forEach(s => s.classList.add('hidden'));
  document.getElementById('step' + step).classList.remove('hidden');
  alert("สมัครสมาชิกสำเร็จ! 🎉");
  return true;
  
}
