<div class="theme-switcher" id="themeSwitcher" aria-label="Theme selector">
  <span class="theme-switcher-label">Theme</span>
  <button type="button" class="theme-swatch" data-theme-value="mint" title="Mint (school color)" aria-label="Mint theme" aria-pressed="false"><span class="theme-swatch-color" style="background:#B0EDE1;"></span><span>Mint</span></button>
  <button type="button" class="theme-swatch" data-theme-value="teal" title="Teal (dark)" aria-label="Teal theme" aria-pressed="false"><span class="theme-swatch-color" style="background:#1F5147;"></span><span>Teal</span></button>
  <button type="button" class="theme-swatch" data-theme-value="orange" title="Orange" aria-label="Orange theme" aria-pressed="false"><span class="theme-swatch-color" style="background:#C75B1B;"></span><span>Orange</span></button>
  <button type="button" class="theme-swatch" data-theme-value="burgundy" title="Burgundy" aria-label="Burgundy theme" aria-pressed="false"><span class="theme-swatch-color" style="background:#6B1F3A;"></span><span>Burgundy</span></button>
</div>

<style>
.theme-switcher{
  position:fixed; bottom:20px; right:20px; z-index:500;
  display:flex; flex-direction:column; align-items:stretch; gap:4px;
  background: var(--surface); border:1px solid var(--surface-border);
  border-radius:14px; padding:8px; min-width:132px; box-shadow:0 8px 24px -8px rgba(0,0,0,0.18);
  font-family:'Inter', sans-serif;
}
.theme-switcher-label{ font-size:11px; color:var(--text-soft); padding:0 6px 3px; }
.theme-swatch{
  display:flex; align-items:center; gap:8px; width:100%; min-height:30px;
  border:1px solid transparent; border-radius:8px; padding:4px 7px;
  background:transparent; color:var(--text-main); font:inherit; font-size:12px;
  text-align:left; cursor:pointer; transition: border-color .15s, background .15s;
}
.theme-swatch:hover{ background:var(--input-bg); }
.theme-swatch.active{ border-color:var(--surface-border); background:var(--input-bg); font-weight:600; }
.theme-swatch-color{display:block;width:18px;height:18px;flex:none;border-radius:50%;border:1px solid rgba(0,0,0,.16);}
@media (max-width:640px){
  .theme-switcher{ bottom:12px; right:12px; padding:6px; min-width:118px; }
}
</style>

<script>
(function(){
  var current = localStorage.getItem('aiplus-theme') || 'mint';
  var allowed = ['mint', 'teal', 'orange', 'burgundy'];
  if (!allowed.includes(current)) current = 'mint';
  function applyTheme(){
    if (current === 'mint') {
      document.documentElement.removeAttribute('data-theme');
    } else {
      document.documentElement.setAttribute('data-theme', current);
    }
  }
  function applyActiveState(){
    document.querySelectorAll('#themeSwitcher .theme-swatch').forEach(function(btn){
      btn.classList.toggle('active', btn.dataset.themeValue === current);
      btn.setAttribute('aria-pressed', String(btn.dataset.themeValue === current));
    });
  }
  applyTheme();
  document.querySelectorAll('#themeSwitcher .theme-swatch').forEach(function(btn){
    btn.addEventListener('click', function(){
      current = btn.dataset.themeValue;
      localStorage.setItem('aiplus-theme', current);
      applyTheme();
      applyActiveState();
    });
  });
  applyActiveState();
})();
</script>
