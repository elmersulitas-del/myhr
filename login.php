<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <title>myhr Login</title>

  <style>
    /* small fade helper (only for slideshow) */
    .slide { opacity: 0; transition: opacity 1.2s ease; }
    .slide.active { opacity: 1; }
  </style>
</head>

<body class="min-h-screen bg-slate-950 text-white">
 <div class="fixed inset-0">
  <img
    src="assets/img/bg.jpg"
    class="h-full w-full object-cover"
    alt="bg"
  />
  <div class="absolute inset-0 bg-gradient-to-br from-slate-950/90 via-slate-950/65 to-slate-950/90"></div>
  <div class="absolute -top-24 -left-24 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"></div>
  <div class="absolute -bottom-28 -right-28 h-80 w-80 rounded-full bg-fuchsia-500/20 blur-3xl"></div>
</div>

  <!-- Page -->
  <main class="relative z-10 min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">

      <!-- LEFT: Branding + slideshow -->
      <section class="relative overflow-hidden rounded-3xl bg-white/10 backdrop-blur-2xl shadow-2xl shadow-black/30">
        <div class="p-6 sm:p-10">
          <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-2xl bg-white/10 grid place-items-center">
              <span class="text-lg font-bold">HR</span>
            </div>
            <div>
              <p class="text-sm text-white/70">Welcome to</p>
              <h1 class="text-xl sm:text-2xl font-semibold">HR Management System</h1>
            </div>
          </div>

          <p class="mt-5 text-sm sm:text-base text-white/75 leading-relaxed">
            Streamline HR workflows, manage announcements, attendance, and leave — all in one place.
          </p>

          <!-- Slideshow -->
          <div class="mt-7 relative h-52 w-full overflow-hidden rounded-2xl">
            <img class="slide active absolute inset-0 h-full w-full object-cover"
                 src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1600&q=60" alt="Office">
            <img class="slide absolute inset-0 h-full w-full object-cover"
                 src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1600&q=60" alt="Interview">
            <img class="slide absolute inset-0 h-full w-full object-cover"
                 src="https://images.unsplash.com/photo-1526948128573-703ee1aeb6fa?auto=format&fit=crop&w=1600&q=60" alt="Work">
            <img class="slide absolute inset-0 h-full w-full object-cover"
                 src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1600&q=60" alt="Team">

            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/65 via-transparent to-transparent"></div>

            <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between">
              <p class="text-xs text-white/80">Culture • Growth • Collaboration</p>
              <div class="flex gap-1.5">
                <span class="dot h-1.5 w-1.5 rounded-full bg-white/90"></span>
                <span class="dot h-1.5 w-1.5 rounded-full bg-white/40"></span>
                <span class="dot h-1.5 w-1.5 rounded-full bg-white/40"></span>
                <span class="dot h-1.5 w-1.5 rounded-full bg-white/40"></span>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="mt-6 rounded-2xl bg-black/20 p-5">
            <p class="text-sm font-semibold text-white/90">Notes</p>
            <ul class="mt-2 space-y-2 text-xs text-white/70">
              <li class="flex gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-300"></span>Only institutional email is allowed.</li>
              <li class="flex gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-300"></span>Applicants can submit resume (PDF/DOC/DOCX).</li>
              <li class="flex gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-300"></span>Keep your file name clear (Lastname_Firstname).</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- RIGHT: Tabs (Sign in / Apply) -->
      <section class="rounded-3xl bg-white/10 backdrop-blur-2xl shadow-2xl shadow-black/30 overflow-hidden">
        <div class="p-6 sm:p-10">
          <div class="flex items-center justify-between gap-3">
            <img src="assets/img/icc-logo.png" alt="logo" class="h-12 w-auto drop-shadow" />
            <span class="text-xs text-white/60">Secure Access</span>
          </div>

          <!-- Tabs -->
          <div class="mt-6 inline-flex rounded-2xl bg-black/20 p-1">
            <button id="tabSignin"
              class="tab-btn px-4 py-2 rounded-xl text-sm font-semibold transition bg-white/15 text-white">
              Sign in
            </button>
            <button id="tabApply"
              class="tab-btn px-4 py-2 rounded-xl text-sm font-semibold transition text-white/70 hover:text-white">
              Apply
            </button>
          </div>

          <!-- Sign In Panel -->
          <div id="panelSignin" class="mt-6">
            <h2 class="text-2xl font-semibold">Sign in</h2>
            <p class="mt-1 text-sm text-white/70">
              Use your institutional Google account to continue.
            </p>

            <a href="google_login.php"
              class="mt-5 w-full inline-flex items-center justify-center gap-3 rounded-2xl bg-white px-5 py-3
                     text-slate-800 font-semibold shadow-md transition hover:shadow-lg active:scale-[0.99]">
              <img src="https://cdn-icons-png.flaticon.com/512/2991/2991148.png" alt="Google" class="h-5 w-5" />
              Sign in with Google
            </a>

            <div class="mt-4 rounded-2xl bg-black/20 p-4 text-xs text-white/70">
              Only institutional email (<b class="text-white/90">immaculada.edu.ph</b>) is allowed.
            </div>

            <!-- Optional: if you still want GSI container -->
            <div class="mt-4 hidden">
              <div class="g_id_signin" data-type="standard"></div>
            </div>
          </div>

          <!-- Apply Panel -->
          <div id="panelApply" class="mt-6 hidden">
            <h2 class="text-2xl font-semibold">Submit your application</h2>
            <p class="mt-1 text-sm text-white/70">
              Upload your resume to apply.
            </p>

            <form action="submit_resume.php" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4">
              <div id="dropzone"
                class="rounded-2xl bg-black/20 p-5 transition hover:bg-black/30">
                <p class="text-sm text-white/80">
                  Drag & drop your file here, or
                  <span class="text-indigo-200 font-semibold">browse</span>
                </p>
                <p class="mt-1 text-xs text-white/60">Allowed: PDF/DOC/DOCX</p>

                <p id="fileName" class="mt-2 text-xs text-emerald-300 hidden"></p>

                <input
                  id="resume"
                  name="resume"
                  type="file"
                  accept=".pdf,.doc,.docx"
                  required
                  class="mt-3 block w-full text-sm text-white/70
                         file:mr-4 file:rounded-xl file:border-0
                         file:bg-indigo-500/20 file:px-4 file:py-2
                         file:text-white file:font-semibold
                         hover:file:bg-indigo-500/30 cursor-pointer"
                />
              </div>

              <button type="submit"
                class="w-full rounded-2xl bg-indigo-500/70 px-5 py-3 text-sm font-semibold text-white
                       transition hover:bg-indigo-500/90 active:scale-[0.99]">
                Submit Application
              </button>

              <p class="text-xs text-white/50">
                By submitting, you confirm the information is accurate.
              </p>
            </form>
          </div>

        </div>
      </section>

    </div>
  </main>

  <!-- (optional) Google GSI -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>

  <script>
    // Tabs (Sign in / Apply)
    const tabSignin = document.getElementById("tabSignin");
    const tabApply = document.getElementById("tabApply");
    const panelSignin = document.getElementById("panelSignin");
    const panelApply = document.getElementById("panelApply");

    function setTab(active) {
      if (active === "signin") {
        panelSignin.classList.remove("hidden");
        panelApply.classList.add("hidden");

        tabSignin.classList.add("bg-white/15", "text-white");
        tabSignin.classList.remove("text-white/70");
        tabApply.classList.remove("bg-white/15", "text-white");
        tabApply.classList.add("text-white/70");
      } else {
        panelApply.classList.remove("hidden");
        panelSignin.classList.add("hidden");

        tabApply.classList.add("bg-white/15", "text-white");
        tabApply.classList.remove("text-white/70");
        tabSignin.classList.remove("bg-white/15", "text-white");
        tabSignin.classList.add("text-white/70");
      }
    }

    tabSignin.addEventListener("click", () => setTab("signin"));
    tabApply.addEventListener("click", () => setTab("apply"));

    // Slideshow
    const slides = document.querySelectorAll(".slide");
    const dots = document.querySelectorAll(".dot");
    let i = 0;

    setInterval(() => {
      slides[i].classList.remove("active");
      dots[i].classList.remove("bg-white/90");
      dots[i].classList.add("bg-white/40");

      i = (i + 1) % slides.length;

      slides[i].classList.add("active");
      dots[i].classList.remove("bg-white/40");
      dots[i].classList.add("bg-white/90");
    }, 3500);

    // File name display + drag highlight
    const input = document.getElementById("resume");
    const fileName = document.getElementById("fileName");
    const dropzone = document.getElementById("dropzone");

    if (input && fileName) {
      input.addEventListener("change", () => {
        if (input.files && input.files[0]) {
          fileName.textContent = "Selected: " + input.files[0].name;
          fileName.classList.remove("hidden");
        } else {
          fileName.classList.add("hidden");
        }
      });
    }

    if (dropzone) {
      ["dragenter", "dragover"].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
          e.preventDefault();
          dropzone.classList.add("ring-2", "ring-indigo-300/60");
        });
      });
      ["dragleave", "drop"].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
          e.preventDefault();
          dropzone.classList.remove("ring-2", "ring-indigo-300/60");
        });
      });
    }
  </script>
</body>
</html>