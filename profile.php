<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.tailwindcss.com"></script>
  <title>MyHR Dashboard</title>
</head>

<body class="bg-slate-50 text-slate-900">

  <!-- ✅ TOP NAVBAR (replaces sidebar) -->
  <nav class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
      <div class="h-16 flex items-center justify-between">
        <!-- Brand -->
        <a href="#" class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-[#1a335b] text-white grid place-items-center font-bold">HR</div>
          <div class="leading-tight">
            <div class="font-semibold">MyHR</div>
            <div class="text-xs text-slate-500 -mt-0.5">Employee Portal</div>
          </div>
        </a>

        <!-- Desktop Tabs -->
        <div class="hidden md:flex items-center gap-2">
          <button data-tab="overview" class="navbtn px-4 py-2 rounded-xl text-sm font-semibold bg-[#1a335b] text-white">
            Overview
          </button>
          <button data-tab="government" class="navbtn px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-100">
            Gov IDs
          </button>
          <button data-tab="profile" class="navbtn px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-100">
            Profile
          </button>
        </div>

        <!-- Mobile menu button -->
        <button id="menuBtn"
          class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-xl border border-slate-300 hover:bg-slate-50"
          aria-label="Open menu">☰</button>
      </div>

      <!-- Mobile dropdown -->
      <div id="mobileMenu" class="md:hidden hidden pb-4">
        <div class="mt-2 grid gap-2">
          <button data-tab="overview" class="navbtn w-full text-left px-4 py-3 rounded-xl bg-[#1a335b] text-white font-semibold">Overview</button>
          <button data-tab="government" class="navbtn w-full text-left px-4 py-3 rounded-xl hover:bg-slate-100 font-semibold">Gov IDs</button>
          <button data-tab="profile" class="navbtn w-full text-left px-4 py-3 rounded-xl hover:bg-slate-100 font-semibold">Profile</button>
        </div>
      </div>
    </div>
  </nav>

  <!-- MAIN -->
  <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-6">

    <!-- Overview -->
    <section id="tab-overview" class="tab">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Summary -->
        <div class="lg:col-span-2 rounded-2xl bg-white shadow-sm border border-slate-200 p-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="text-base font-semibold">Your Summary</h2>
              <p class="text-sm text-slate-500 mt-1">Quick view of your stored information.</p>
            </div>
            <button data-tab-jump="profile"
              class="jump rounded-xl bg-[#1a335b] text-white px-4 py-2 text-sm font-semibold hover:opacity-95">
              Update Profile
            </button>
          </div>

          <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
              <div class="text-xs text-slate-500">Department</div>
              <div class="font-semibold mt-1" id="sumDepartment">HR Department</div>
            </div>
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
              <div class="text-xs text-slate-500">Employee ID</div>
              <div class="font-semibold mt-1" id="sumEmpId">EMP-0001</div>
            </div>
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
              <div class="text-xs text-slate-500">Files Uploaded</div>
              <div class="font-semibold mt-1"><span id="sumFiles">0</span> file(s)</div>
            </div>
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
              <div class="text-xs text-slate-500">Profile Completion</div>
              <div class="font-semibold mt-1" id="sumCompletion">80%</div>
            </div>
          </div>
        </div>

        <!-- Quick IDs -->
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-6">
          <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">Gov IDs</h2>
            <button data-tab-jump="government" class="jump text-sm font-semibold text-[#1a335b] hover:underline">Edit</button>
          </div>

          <div class="mt-4 space-y-3 text-sm">
            <div class="flex items-center justify-between rounded-xl bg-slate-50 border border-slate-200 p-3">
              <span class="text-slate-600">SSS</span><span class="font-semibold" id="qSSS">—</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-slate-50 border border-slate-200 p-3">
              <span class="text-slate-600">Pag-IBIG</span><span class="font-semibold" id="qHDMF">—</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-slate-50 border border-slate-200 p-3">
              <span class="text-slate-600">PhilHealth</span><span class="font-semibold" id="qPH">—</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-slate-50 border border-slate-200 p-3">
              <span class="text-slate-600">BIR (TIN)</span><span class="font-semibold" id="qTIN">—</span>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- Gov IDs + ✅ FILE UPLOAD PER ID -->
    <section id="tab-government" class="tab hidden">
      <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-6">
        <h2 class="text-base font-semibold">Government Information</h2>
        <p class="text-sm text-slate-500 mt-1">
          Update your numbers and upload proof/certificates (PDF/JPG/PNG).
        </p>

        <!-- NOTE: you can keep one endpoint OR split into per-ID endpoints -->
        <form class="mt-6 space-y-6" action="save_gov_ids.php" method="POST" enctype="multipart/form-data">

          <!-- SSS -->
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-semibold">SSS</h3>
              <span class="text-xs text-slate-500">Optional proof upload</span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium">SSS Number</label>
                <input name="sss_no" placeholder="12-3456789-0"
                  class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a335b]/30" />
                <p class="mt-1 text-xs text-slate-500">Example: 12-3456789-0</p>
              </div>

              <div>
                <label class="text-sm font-medium">Upload SSS Proof</label>
                <input name="sss_file" type="file" accept=".pdf,.jpg,.jpeg,.png"
                  class="mt-1 block w-full text-sm text-slate-600
                         file:mr-4 file:rounded-xl file:border-0
                         file:bg-[#1a335b]/10 file:px-4 file:py-2
                         file:text-[#1a335b] file:font-semibold hover:file:bg-[#1a335b]/15" />
                <p class="mt-1 text-xs text-slate-500">Accepted: PDF/JPG/PNG</p>
              </div>
            </div>
          </div>

          <!-- Pag-IBIG -->
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-semibold">Pag-IBIG (HDMF)</h3>
              <span class="text-xs text-slate-500">Optional proof upload</span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium">Pag-IBIG MID</label>
                <input name="pagibig_mid" placeholder="1234-5678-9012"
                  class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a335b]/30" />
                <p class="mt-1 text-xs text-slate-500">Example: 1234-5678-9012</p>
              </div>

              <div>
                <label class="text-sm font-medium">Upload Pag-IBIG Proof</label>
                <input name="pagibig_file" type="file" accept=".pdf,.jpg,.jpeg,.png"
                  class="mt-1 block w-full text-sm text-slate-600
                         file:mr-4 file:rounded-xl file:border-0
                         file:bg-[#1a335b]/10 file:px-4 file:py-2
                         file:text-[#1a335b] file:font-semibold hover:file:bg-[#1a335b]/15" />
                <p class="mt-1 text-xs text-slate-500">Accepted: PDF/JPG/PNG</p>
              </div>
            </div>
          </div>

          <!-- PhilHealth -->
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-semibold">PhilHealth</h3>
              <span class="text-xs text-slate-500">Optional proof upload</span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium">PhilHealth Number</label>
                <input name="philhealth_no" placeholder="12-345678901-2"
                  class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a335b]/30" />
                <p class="mt-1 text-xs text-slate-500">Example: 12-345678901-2</p>
              </div>

              <div>
                <label class="text-sm font-medium">Upload PhilHealth Proof</label>
                <input name="philhealth_file" type="file" accept=".pdf,.jpg,.jpeg,.png"
                  class="mt-1 block w-full text-sm text-slate-600
                         file:mr-4 file:rounded-xl file:border-0
                         file:bg-[#1a335b]/10 file:px-4 file:py-2
                         file:text-[#1a335b] file:font-semibold hover:file:bg-[#1a335b]/15" />
                <p class="mt-1 text-xs text-slate-500">Accepted: PDF/JPG/PNG</p>
              </div>
            </div>
          </div>

          <!-- BIR -->
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-semibold">BIR (TIN)</h3>
              <span class="text-xs text-slate-500">Optional proof upload</span>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium">TIN</label>
                <input name="tin_no" placeholder="123-456-789-000"
                  class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a335b]/30" />
                <p class="mt-1 text-xs text-slate-500">Example: 123-456-789-000</p>
              </div>

              <div>
                <label class="text-sm font-medium">Upload BIR Proof</label>
                <input name="tin_file" type="file" accept=".pdf,.jpg,.jpeg,.png"
                  class="mt-1 block w-full text-sm text-slate-600
                         file:mr-4 file:rounded-xl file:border-0
                         file:bg-[#1a335b]/10 file:px-4 file:py-2
                         file:text-[#1a335b] file:font-semibold hover:file:bg-[#1a335b]/15" />
                <p class="mt-1 text-xs text-slate-500">Accepted: PDF/JPG/PNG</p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3">
            <button type="button" data-tab-jump="overview"
              class="jump rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">
              Cancel
            </button>
            <button type="submit"
              class="rounded-xl bg-[#1a335b] text-white px-5 py-2.5 text-sm font-semibold hover:opacity-95">
              Save Government Info
            </button>
          </div>
        </form>
      </div>
    </section>

    <!-- Profile -->
    <section id="tab-profile" class="tab hidden">
      <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-6">
        <h2 class="text-base font-semibold">Profile Information</h2>
        <p class="text-sm text-slate-500 mt-1">Update your personal info. Changes may require HR verification.</p>

        <form class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4" action="update_profile.php" method="POST">
          <div>
            <label class="text-sm font-medium">Full Name</label>
            <input name="full_name" value="Juan Dela Cruz"
              class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a335b]/30" />
          </div>

          <div>
            <label class="text-sm font-medium">Institutional Email</label>
            <input name="email" value="juan@school.edu.ph" readonly
              class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500" />
          </div>

          <div>
            <label class="text-sm font-medium">Contact Number</label>
            <input name="contact_no" placeholder="09XXXXXXXXX"
              class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a335b]/30" />
          </div>

          <div>
            <label class="text-sm font-medium">Address</label>
            <input name="address" placeholder="Your complete address"
              class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a335b]/30" />
          </div>

          <div class="md:col-span-2 flex items-center justify-end gap-3 mt-2">
            <button type="reset"
              class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">
              Cancel
            </button>
            <button type="submit"
              class="rounded-xl bg-[#1a335b] text-white px-5 py-2.5 text-sm font-semibold hover:opacity-95">
              Save Profile
            </button>
          </div>
        </form>
      </div>
    </section>

  </main>

  <script>
    // Navbar tabs navigation
    const tabs = ["overview", "government", "profile"];
    const navBtns = document.querySelectorAll(".navbtn");
    const jumpBtns = document.querySelectorAll(".jump");

    function setActiveBtn(tabName) {
      navBtns.forEach(b => {
        const isActive = b.dataset.tab === tabName;
        b.classList.toggle("bg-[#1a335b]", isActive);
        b.classList.toggle("text-white", isActive);
        b.classList.toggle("hover:bg-slate-100", !isActive);
        b.classList.toggle("text-slate-700", !isActive);
        b.classList.toggle("bg-slate-100", false);
      });
    }

    function showTab(name) {
      tabs.forEach(t => {
        document.getElementById("tab-" + t).classList.toggle("hidden", t !== name);
      });
      setActiveBtn(name);
      // close mobile
      const mobileMenu = document.getElementById("mobileMenu");
      if (mobileMenu) mobileMenu.classList.add("hidden");
    }

    navBtns.forEach(btn => btn.addEventListener("click", () => showTab(btn.dataset.tab)));
    jumpBtns.forEach(btn => btn.addEventListener("click", () => showTab(btn.dataset.tabJump)));
    showTab("overview");

    // Mobile menu toggle
    const menuBtn = document.getElementById("menuBtn");
    const mobileMenu = document.getElementById("mobileMenu");
    if (menuBtn && mobileMenu) {
      menuBtn.addEventListener("click", () => mobileMenu.classList.toggle("hidden"));
    }
  </script>
</body>
</html>