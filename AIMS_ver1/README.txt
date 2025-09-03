Student Portal (PHP + MySQL + XAMPP)

Features implemented
- Registrar → Upload Grades (by student username, subject, grade, semester, school year)
- Registrar → Upload Files (PDFs/images saved to /student_portal/uploads; students see under My Files)
- SAO → Announcements (create posts; students see on Announcements page)
- Student → Sees own grades/files + all announcements
- Role-based routing: Super Admin, Registrar, SAO, Student

Setup (Windows + XAMPP)
1) Extract this folder to C:\xampp\htdocs\student_portal
2) Start Apache + MySQL in XAMPP
3) Create database in phpMyAdmin named student_portal
4) Import install/student_portal.sql into that database
5) If your MySQL root has a password, edit data/db.php ($pass)
6) Open http://localhost/student_portal and use seed accounts:

   Super Admin: username=admin,     password=admin
   Registrar:   username=registrar, password=registrar
   SAO:         username=sao,       password=sao
   Student:     username=student1,  password=student1

Notes & next steps
- Passwords are plain text for development. For production, switch to password_hash()/password_verify() and add CSRF tokens.
- File uploads go to /student_portal/uploads. On public servers, add tighter permissions or move outside web root.
- Extend Admin area to manage users/roles via UI (for now use phpMyAdmin).
- Optional enhancements: CSV bulk grade upload (Registrar), edit/delete announcements (SAO), email via PHPMailer, theming/branding.
