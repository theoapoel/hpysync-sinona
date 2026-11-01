' Menjalankan Laravel scheduler tiap menit tanpa jendela CMD berkedip.
' Dipanggil oleh Windows Task Scheduler.
Set sh = CreateObject("WScript.Shell")
sh.CurrentDirectory = "C:\xampp\htdocs\resto-pos"
sh.Run "C:\xampp\php\php.exe artisan schedule:run", 0, False
