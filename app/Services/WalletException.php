<?php

namespace App\Services;

/** การเคลื่อนไหวเงินที่ทำไม่ได้ (ยอดไม่พอ เกินวงเงิน กระเป๋าถูกระงับ ฯลฯ) ข้อความแสดงให้ผู้ใช้เห็นได้ */
class WalletException extends \RuntimeException {}
