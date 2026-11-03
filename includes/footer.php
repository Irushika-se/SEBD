</main>
<footer class="foot"><div class="foot-inner"><div><strong>SEBD</strong><br><span>Smart Emergency Blood Bank &amp; Donor Matching System</span></div><div class="foot-links"><a href="<?= BASE ?>/index.php">Home</a><a href="<?= BASE ?>/about.php">About</a><a href="<?= BASE ?>/contact.php">Contact</a><?php if(empty($_SESSION['uid'])): ?><a href="<?= BASE ?>/login.php">Login</a><a href="<?= BASE ?>/register.php">Register</a><?php endif; ?></div></div></footer>
<script src="<?= BASE ?>/assets/js/script.js?v=<?= @filemtime(__DIR__."/../assets/js/script.js") ?>"></script></body></html>
