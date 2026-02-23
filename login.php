<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<script src="https://kit.fontawesome.com/64d58efce2.js" crossorigin="anonymous"></script>
	<link rel="stylesheet" href="assets\css\login.css" />
	<title>Sign in & Sign up Form</title>
</head>
<style>
/* Google button smaller and aligned */
.google-btn {
    display: inline-flex; /* keep it compact */
    align-items: center;
    gap: 10px; /* bigger gap between icon and text */
    padding: 12px 20px; /* increased padding for larger button */
    border-radius: 8px; /* slightly more rounded */
    border: 1px solid #ddd;
    background: #fff;
    cursor: pointer;
    font-weight: bold;
    font-size: 18px; /* bigger font */
    transition: 0.3s;
    text-decoration: none;
    color: #444;
}

.google-btn:hover {
    background-color: #f5f5f5;
}

.google-btn img.google-icon {
    width: 24px; /* bigger icon */
    height: 24px;
}

.google-btn:hover {
    background-color: #f5f5f5;
}

.google-btn img.google-icon {
    width: 18px; /* smaller icon */
    height: 18px;
}
.note {
    margin-top: 20px;
    font-size: 12px;
    color: #999;
}
	</style>

<body>
	<div class="container">
		<div class="forms-container">
			<div class="signin-signup">
				<form action="#" class="sign-in-form">
					<div style="text-align: center; margin-bottom: 20px;">
  <!-- ICC Logo -->
  <img src="assets\img\icc-logo.png" 
       alt="iccogle" style="width:150px; display:block; margin: 0 auto 10px;">


    <h2 class="title">Sign in</h2>

    <!-- Google Sign-In Button -->
    <a href="google_login.php" class="google-btn">
        <img src="https://cdn-icons-png.flaticon.com/512/2991/2991148.png" 
             alt="Google" class="google-icon">
        Sign in with Google
    </a>
	<p class="note">
            Only institutional email (@yourschool.edu.ph) is allowed.
        </p>
</div>

<!-- Optional Google Identity Services placeholder -->
<div class="g_id_signin" data-type="standard"></div>

<div class="g_id_signin" data-type="standard"></div>			
				</form>
				<form action="#" class="sign-up-form">
					<h2 class="title">Sign up</h2>
					<div class="input-field">
						<i class="fas fa-user"></i>
						<input type="empid" placeholder="Employee ID" />
					</div>
					<div class="input-field">
						<i class="fas fa-envelope"></i>
						<input type="flname" placeholder="Full Name" />
					</div>
					<div class="input-field">
						<i class="fas fa-lock"></i>
						<input type="dept" placeholder="Department" />
					</div>
					<input type="submit" class="btn" value="Sign up" />

				</form>
			</div>
		</div>

		<div class="panels-container">
			<div class="panel left-panel">
				<div class="content">
					<h3>New to our community ?</h3>
					<p>
                            Our HR Management System streamlines processes, strengthens collaboration, and empowers our community to grow and succeed together.
					</p>
					<button class="btn transparent" id="sign-up-btn">
						Sign up
					</button>
				</div>
				<img  src="https://i.ibb.co/6HXL6q1/Privacy-policy-rafiki.png" class="image" alt="" />
			</div>
			<div class="panel right-panel">
				<div class="content">
					<h3>One of Our Valued Members</h3>
					<p>
						Thank you for being part of our community. Your presence enriches our
          shared experiences. Let's continue this journey together!
					</p>
					<button class="btn transparent" id="sign-in-btn">
						Sign in
					</button>
				</div>
				<img src="https://i.ibb.co/nP8H853/Mobile-login-rafiki.png"  class="image" alt="" />
			</div>
		</div>
	</div>

	<script src="assets\javascript\login.js"></script>
	<script src="https://accounts.google.com/gsi/client" async defer></script>
	
</body>

</html>