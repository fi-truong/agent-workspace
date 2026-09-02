<div class="theme-switcher" id="themeSwitcher" aria-label="Theme selector">
  <span class="theme-switcher-label">Theme</span>
  <button type="button" class="theme-swatch" data-theme-value="mint" title="Mint (school color)" style="background:#B0EDE1;" aria-label="Mint theme"></button>
  <button type="button" class="theme-swatch" data-theme-value="teal" title="Teal (dark)" style="background:#1F5147;" aria-label="Teal theme"></button>
</div>

<style>
.theme-switcher{
  position:fixed; bottom:20px; right:20px; z-index:500;
  display:flex; align-items:center; gap:8px;
  background: var(--surface); border:1px solid var(--surface-border);
  border-radius:999px; padding:8px 14px; box-shadow:0 8px 24px -8px rgba(0,0,0,0.18);
  font-family:'Inter', sans-serif;
}
.theme-switcher-label{ font-size:12px; color:var(--text-soft); }
.theme-swatch{
  width:22px; height:22px; border-radius:50%; cursor:pointer;
  border:2px solid transparent; padding:0; transition: border-color .15s, transform .15s;
}
.theme-swatch:hover{ transform: scale(1.1); }
.theme-swatch.active{ border-color: var(--text-soft); }
@media (max-width:640px){
  .theme-switcher{ bottom:12px; right:12px; padding:6px 10px; }
  .theme-switcher-label{ display:none; }
}
</style>

<script>
(function(){
  var current = localStorage.getItem('aiplus-theme') || 'mint';
  function applyActiveState(){
    document.querySelectorAll('#themeSwitcher .theme-swatch').forEach(function(btn){
      btn.classList.toggle('active', btn.dataset.themeValue === current);
    });
  }
  document.querySelectorAll('#themeSwitcher .theme-swatch').forEach(function(btn){
    btn.addEventListener('click', function(){
      current = btn.dataset.themeValue;
      localStorage.setItem('aiplus-theme', current);
      if (current === 'teal') {
        document.documentElement.setAttribute('data-theme', 'teal');
      } else {
        document.documentElement.removeAttribute('data-theme');
      }
      applyActiveState();
    });
  });
  applyActiveState();
})();
</script>
