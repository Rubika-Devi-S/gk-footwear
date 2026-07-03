    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.querySelectorAll('.toast').forEach(toastEl => {
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
});

document.querySelectorAll('[data-confirm]').forEach(form => {
    form.addEventListener('submit', function (event) {
        const message = this.getAttribute('data-confirm') || 'Are you sure?';
        if (!confirm(message)) {
            event.preventDefault();
        }
    });
});
</script>
</body>
</html>
