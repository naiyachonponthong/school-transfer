{{-- ทุนจ่ายเป็นเงิน: มูลค่าเป็นบาทเท่านั้น และไม่มีรายการค่าธรรมเนียมให้เลือก --}}
<script>
document.querySelectorAll('[data-scholarship-mode]').forEach((mode) => {
    const id = mode.dataset.scholarshipMode;
    const type = document.querySelector(`[data-scholarship-type="${id}"]`), fee = document.querySelector(`[data-scholarship-fee="${id}"]`);
    const sync = () => {
        const cash = mode.value === 'cash';
        if (cash) type.value = 'amount';
        type.querySelector('[value=percent]').disabled = cash;
        fee.classList.toggle('d-none', cash);
    };
    mode.addEventListener('change', sync); sync();
});
</script>
