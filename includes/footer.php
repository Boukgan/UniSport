<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-col">
      <div class="footer-logo">Uni<span>Sport</span></div>
      <p class="footer-text">Convenient online platform for UTeM students and staff to reserve sports facilities on campus.</p>
    </div>
    <div class="footer-col">
      <h4>About Us</h4>
      <p class="footer-text">UniSport is operated by the UTeM Sports Centre.</p>
    </div>
    <div class="footer-col">
      <h4>Help Center</h4>
      <a href="<?= base_url('help_center.php') ?>" class="footer-link">How to use the system</a>
    </div>
    <div class="footer-col">
      <h4>Contact Information</h4>
      <p class="footer-text">Email: <a href="mailto:unisportsupport@gmail.com" class="footer-link">unisportsupport@gmail.com</a></p>
    </div>
  </div>
  <div class="footer-bottom">© <?= date('Y') ?> UniSport · UTeM</div>
</footer>
<script src="<?= base_url('js/script.js') ?>"></script>
<?php if (!empty($extraJs)) foreach ((array)$extraJs as $j): ?>
  <script src="<?= base_url('js/' . $j) ?>"></script>
<?php endforeach; ?>
</body>
</html>
