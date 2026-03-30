<footer class="site-footer">
    <div class="container">
        <p>&copy; {{ date('Y') }} Neora Color Studio. All rights reserved.</p>
    </div>
</footer>
<script src="{{ asset('js/main.js') }}" defer></script>
@if (!empty($pageScript))
<script src="{{ asset('js/' . $pageScript) }}" defer></script>
@endif
</body>
</html>
