<footer class="site-footer {{ !empty($showAdminMenu) ? 'site-footer-with-admin' : '' }}" {{ !empty($showAdminMenu) ? 'data-admin-footer-shell' : '' }}>
    <div class="container">
        <p>&copy; {{ date('Y') }} Neora Color Studio. {{ __('ui.footer.copyright') }}</p>
    </div>
</footer>
<script src="{{ asset('js/main.js') }}" defer></script>
@if (!empty($pageScript))
<script src="{{ asset('js/' . $pageScript) }}" defer></script>
@endif
</body>
</html>
