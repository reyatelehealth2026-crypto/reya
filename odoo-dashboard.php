<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNY ERP - ระบบจัดการข้อมูล</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📦</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --black: #020617;
            --success: #10b981;
            --success-light: #d1fae5;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --primary: #6366f1;
            --primary-light: #e0e7ff;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --info: #06b6d4;
            --info-light: #cffafe;
            --violet: #8b5cf6;
            --violet-light: #ede9fe;
            --surface: #ffffff;
            --surface-raised: #ffffff;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.06);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.08);
            --shadow-xl: 0 16px 48px rgba(0,0,0,0.12);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'IBM Plex Sans Thai', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #faf5ff 50%, #fdf2f8 100%);
            min-height: 100vh;
            color: var(--gray-800);
        }

        /* ── Header ── */
        .header {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(0,0,0,0.06);
            padding: 0.75rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--gray-900);
            letter-spacing: -0.02em;
        }
        .header-subtitle { font-size: 0.75rem; color: var(--gray-400); font-weight: 400; }
        .status-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.35rem 0.75rem; border-radius: 50px;
            font-size: 0.78rem; font-weight: 500; transition: all 0.3s ease;
        }
        .status-badge.online { background: var(--success-light); color: #059669; }
        .status-badge.offline { background: var(--danger-light); color: #dc2626; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .status-badge.online .status-dot { animation: pulse-green 2s infinite; }
        @keyframes pulse-green { 0%,100% { opacity:1; } 50% { opacity:0.4; } }

        /* ── Main Container ── */
        .main-container { padding: 1.5rem 2rem 3rem; max-width: 1480px; margin: 0 auto; }

        /* ── Menu Grid ── */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .menu-card {
            background: var(--surface);
            border: 1.5px solid transparent;
            border-radius: var(--radius-md);
            padding: 1rem 0.75rem;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        .menu-card::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, transparent, rgba(99,102,241,0.03));
            opacity: 0; transition: opacity 0.25s ease;
        }
        .menu-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--gray-200); }
        .menu-card:hover::before { opacity: 1; }
        .menu-card.active {
            background: linear-gradient(135deg, var(--gray-900), var(--gray-800));
            color: var(--white); border-color: transparent;
            box-shadow: 0 4px 20px rgba(15,23,42,0.25);
        }
        .menu-card.active .menu-desc { color: var(--gray-400); }
        .menu-icon { font-size: 1.35rem; margin-bottom: 0.35rem; }
        .menu-title { font-size: 0.82rem; font-weight: 600; }
        .menu-desc { font-size: 0.7rem; color: var(--gray-400); margin-top: 0.15rem; }

        /* ── Content Cards ── */
        .content-card {
            background: var(--surface-raised);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
        }
        .content-title {
            font-size: 0.95rem; font-weight: 600; color: var(--gray-800);
            margin-bottom: 0.75rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .content-title i { color: var(--gray-400); font-size: 1rem; }

        /* ── Form ── */
        .form-group { margin-bottom: 0.75rem; }
        .form-label { display: block; color: var(--gray-500); font-size: 0.78rem; font-weight: 500; margin-bottom: 0.3rem; }
        .form-control {
            background: var(--white);
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: 0.55rem 0.85rem;
            font-size: 0.88rem;
            color: var(--gray-800);
            width: 100%;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .form-control::placeholder { color: var(--gray-400); }

        /* ── Buttons ── */
        .btn-primary {
            background: linear-gradient(135deg, var(--gray-800), var(--gray-900));
            border: none; color: var(--white);
            padding: 0.55rem 1.2rem;
            border-radius: var(--radius-sm);
            font-size: 0.84rem; font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-family: inherit;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,23,42,0.2); }
        .btn-primary:disabled { background: var(--gray-300); cursor: not-allowed; transform: none; }

        .chip {
            background: var(--white);
            border: 1.5px solid var(--gray-200);
            color: var(--gray-600);
            padding: 0.4rem 0.85rem;
            border-radius: 50px;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex; align-items: center; gap: 0.35rem;
            font-family: inherit;
        }
        .chip:hover { background: var(--gray-800); color: var(--white); border-color: var(--gray-800); }
        .chip i { font-size: 0.85rem; }

        /* ── Quick Section ── */
        .quick-section { background: var(--gray-50); border-radius: var(--radius-md); padding: 0.85rem; margin-bottom: 1rem; }
        .quick-title { font-size: 0.75rem; color: var(--gray-400); margin-bottom: 0.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .quick-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }

        /* ── Info Box ── */
        .info-box {
            background: var(--white);
            padding: 0.65rem 0.8rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
            transition: transform 0.15s ease;
        }
        .info-box:hover { transform: translateY(-1px); }
        .info-label { font-size: 0.7rem; color: var(--gray-400); margin-bottom: 0.15rem; font-weight: 500; }
        .info-value { font-size: 0.95rem; font-weight: 700; color: var(--gray-800); }

        /* ── Partner ── */
        .partner-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-top: 0.75rem; }

        /* ── JSON Display ── */
        pre.json-display {
            background: var(--gray-900); color: #a5b4fc;
            padding: 1rem; border-radius: var(--radius-sm);
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 0.72rem; overflow-x: auto; margin: 0;
            max-height: 400px; line-height: 1.6;
        }

        /* ── Section Panel ── */
        .section-panel { display: none; }
        .section-panel.active { display: block; animation: fadeSlideIn 0.35s ease; }
        @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Loading ── */
        .loading { text-align: center; padding: 2rem; color: var(--gray-400); }
        .loading i { font-size: 1.3rem; margin-bottom: 0.4rem; display: block; }
        .spin { animation: spin 0.8s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* ── Modal Backdrop ── */
        .modal-backdrop-custom {
            position: fixed; inset: 0;
            background: rgba(15,23,42,0.5);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 1000;
            overflow-y: auto;
            display: none;
        }
        .modal-backdrop-custom.active { display: flex; align-items: flex-start; justify-content: center; }

        /* ── Detail Modal ── */
        .detail-modal-container {
            max-width: 900px; width: 100%;
            margin: 1.5rem auto;
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            position: relative;
            max-height: 92vh; overflow: hidden;
            display: flex; flex-direction: column;
        }
        .detail-modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex; justify-content: space-between; align-items: center;
            flex-shrink: 0;
        }
        .detail-modal-header h5 { font-weight: 700; font-size: 1.05rem; margin: 0; }
        .detail-modal-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; }
        .detail-modal-close {
            background: var(--gray-100); border: none;
            width: 32px; height: 32px; border-radius: 50%;
            font-size: 1.15rem; cursor: pointer; color: var(--gray-500);
            display: flex; align-items: center; justify-content: center;
            transition: all 0.15s ease;
        }
        .detail-modal-close:hover { background: var(--gray-200); color: var(--gray-800); }

        /* ── Clickable ref links ── */
        .ref-link {
            color: var(--primary); text-decoration: none; cursor: pointer;
            font-weight: 600; transition: all 0.15s ease;
            border-bottom: 1.5px solid transparent;
        }
        .ref-link:hover { color: #4338ca; border-bottom-color: #4338ca; }

        /* ── Badge System ── */
        .badge-status {
            display: inline-flex; align-items: center; gap: 0.25rem;
            padding: 0.2rem 0.6rem; border-radius: 50px;
            font-size: 0.72rem; font-weight: 600; white-space: nowrap;
        }
        .badge-success { background: var(--success-light); color: #059669; }
        .badge-danger { background: var(--danger-light); color: #dc2626; }
        .badge-warning { background: var(--warning-light); color: #d97706; }
        .badge-info { background: var(--info-light); color: #0891b2; }
        .badge-violet { background: var(--violet-light); color: #7c3aed; }
        .badge-neutral { background: var(--gray-100); color: var(--gray-500); }
        .badge-primary { background: var(--primary-light); color: #4338ca; }

        /* ── Tabs ── */
        .tab-bar {
            display: flex; gap: 0;
            border-bottom: 2px solid var(--gray-100);
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .tab-bar::-webkit-scrollbar { display: none; }
        .tab-btn {
            padding: 0.5rem 0.85rem;
            border: none;
            border-bottom: 2.5px solid transparent;
            background: none; cursor: pointer;
            color: var(--gray-400);
            font-size: 0.82rem; font-weight: 500;
            white-space: nowrap;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        .tab-btn:hover { color: var(--gray-600); }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }

        /* ── PDF Viewer ── */
        .pdf-viewer-container {
            background: var(--gray-900);
            border-radius: var(--radius-md);
            overflow: hidden;
            min-height: 500px;
        }
        .pdf-viewer-container iframe { width: 100%; height: 600px; border: none; }

        /* ── Data Table ── */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
        .data-table thead tr { background: var(--gray-50); border-bottom: 2px solid var(--gray-200); }
        .data-table th { padding: 0.55rem 0.6rem; font-weight: 600; color: var(--gray-500); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .data-table td { padding: 0.55rem 0.6rem; }
        .data-table tbody tr { border-bottom: 1px solid var(--gray-100); transition: background 0.15s ease; }
        .data-table tbody tr:hover { background: var(--gray-50); }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .main-container { padding: 0.75rem; }
            .header { padding: 0.75rem 1rem; }
            .menu-grid { grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
            .menu-card { padding: 0.65rem 0.5rem; }
            .menu-icon { font-size: 1.1rem; }
            .menu-title { font-size: 0.72rem; }
            .menu-desc { display: none; }
            .content-card { padding: 0.85rem; border-radius: var(--radius-md); }
            .detail-modal-container { margin: 0.5rem; border-radius: var(--radius-lg); max-height: 95vh; }
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gray-400); }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="header-title">
                    <i class="bi bi-box-seam me-1" style="color:var(--primary);"></i>CNY ERP
                </div>
                <div class="header-subtitle">ระบบจัดการข้อมูล Odoo</div>
            </div>
            <div id="connectionStatus" class="status-badge offline">
                <span class="status-dot"></span>
                <span>กำลังเชื่อมต่อ...</span>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Menu Grid -->
        <div class="menu-grid">
            <div class="menu-card" onclick="showSection('webhooks')">
                <div class="menu-icon"><i class="bi bi-broadcast"></i></div>
                <div class="menu-title">Webhooks</div>
                <div class="menu-desc">ติดตามสถานะออเดอร์</div>
            </div>
            <div class="menu-card active" onclick="showSection('customers')">
                <div class="menu-icon"><i class="bi bi-people"></i></div>
                <div class="menu-title">การจัดการลูกค้า</div>
                <div class="menu-desc">รายการลูกค้าและใบแจ้งหนี้</div>
            </div>
            <div class="menu-card" onclick="showSection('notifications')">
                <div class="menu-icon"><i class="bi bi-bell"></i></div>
                <div class="menu-title">สรุป Log แจ้งเตือน</div>
                <div class="menu-desc">ประวัติการแจ้งเตือน LINE</div>
            </div>
            <div class="menu-card" onclick="showSection('daily-summary')">
                <div class="menu-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="menu-title">สรุปประจำวัน</div>
                <div class="menu-desc">ตรวจสอบและส่งสรุปออเดอร์</div>
            </div>
            <div class="menu-card" onclick="showSection('health')">
                <div class="menu-icon"><i class="bi bi-heart-pulse"></i></div>
                <div class="menu-title">System Health</div>
                <div class="menu-desc">สุขภาพระบบรวม</div>
            </div>
            <div class="menu-card" onclick="window.location.href='dashboard.php'">
                <div class="menu-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="menu-title">Executive</div>
                <div class="menu-desc">ภาพรวมธุรกิจและ KPI</div>
            </div>
            <div class="menu-card" onclick="showSection('slips')">
                <div class="menu-icon"><i class="bi bi-receipt"></i></div>
                <div class="menu-title">สลิปการชำระเงิน</div>
                <div class="menu-desc">บันทึกสลิปจาก LINE Inbox</div>
            </div>
        </div>

        <!-- ═══════════════════════ Webhooks Section ═══════════════════════ -->
        <div id="section-webhooks" class="section-panel">
            <div id="webhookStats" class="mb-3">
                <div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลดสถิติ...</div></div>
            </div>

            <div class="content-card" style="margin-bottom:0.75rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                    <div style="display:flex;gap:4px;">
                        <button id="whViewBtnGrouped" onclick="setWhViewMode('grouped')" style="background:var(--primary);color:white;border:none;border-radius:6px;padding:5px 14px;cursor:pointer;font-size:0.82rem;font-weight:500;font-family:inherit;"><i class="bi bi-box-seam"></i> ออเดอร์</button>
                        <button id="whViewBtnList" onclick="setWhViewMode('list')" style="background:var(--gray-100);color:var(--gray-600);border:none;border-radius:6px;padding:5px 14px;cursor:pointer;font-size:0.82rem;font-weight:500;font-family:inherit;"><i class="bi bi-list-ul"></i> รายการ</button>
                    </div>
                    <div id="grpSearchBar" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                        <input type="date" class="form-control" id="grpDateInput" style="width:auto;font-size:0.82rem;padding:4px 8px;" onchange="grpCurrentOffset=0;loadOrdersGrouped()">
                        <input type="text" class="form-control" id="grpSearchInput" placeholder="ค้นหาออเดอร์/ลูกค้า..." style="width:180px;font-size:0.82rem;padding:4px 8px;" onkeyup="if(event.key==='Enter'){grpCurrentOffset=0;loadOrdersGrouped();}">
                        <button class="chip" onclick="grpCurrentOffset=0;loadOrdersGrouped();" style="font-size:0.8rem;"><i class="bi bi-search"></i></button>
                        <button class="chip" onclick="loadWebhookStats();grpCurrentOffset=0;loadOrdersGrouped();" style="font-size:0.8rem;"><i class="bi bi-arrow-repeat"></i> รีเฟรช</button>
                        <span id="whDateScopeBadge" class="badge-status badge-primary">วันนี้</span>
                    </div>
                </div>
            </div>

            <div class="content-card" id="whFilterCard" style="display:none;">
                <div class="content-title"><i class="bi bi-funnel"></i> ตัวกรอง</div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">ประเภท Event</label>
                            <select class="form-control" id="whFilterEvent" onchange="loadWebhooks()"><option value="">ทั้งหมด</option></select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="form-label">สถานะ</label>
                            <select class="form-control" id="whFilterStatus" onchange="loadWebhooks()">
                                <option value="">ทั้งหมด</option>
                                <option value="received">Received</option>
                                <option value="processing">Processing</option>
                                <option value="success">Success</option>
                                <option value="failed">Failed</option>
                                <option value="retry">Retry</option>
                                <option value="dead_letter">Dead Letter</option>
                                <option value="duplicate">Duplicate</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="form-label">จากวันที่</label>
                            <input type="date" class="form-control" id="whFilterDateFrom" onchange="loadWebhooks()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="form-label">ถึงวันที่</label>
                            <input type="date" class="form-control" id="whFilterDateTo" onchange="loadWebhooks()">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">ค้นหา (Order/Delivery ID)</label>
                            <input type="text" class="form-control" id="whFilterSearch" placeholder="ค้นหา..." onkeyup="if(event.key==='Enter')loadWebhooks()">
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn-primary" onclick="loadWebhooks()"><i class="bi bi-search"></i> ค้นหา</button>
                    <button class="chip" onclick="resetWebhookFilters()"><i class="bi bi-x-circle"></i> ล้างตัวกรอง</button>
                    <button class="chip" onclick="loadWebhookStats();loadWebhooks();"><i class="bi bi-arrow-repeat"></i> รีเฟรช</button>
                </div>
            </div>

            <div class="content-card" style="margin-top:1rem;">
                <div class="content-title">
                    <i class="bi bi-box-seam"></i> ภาพรวมออเดอร์
                    <span id="whTotalCount" style="font-size:0.8rem;color:var(--gray-500);margin-left:auto;"></span>
                </div>
                <div id="webhookList"><div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลด...</div></div></div>
                <div id="webhookPagination" class="d-flex justify-content-center gap-2 mt-3" style="display:none !important;"></div>
            </div>
        </div>

        <!-- ═══════════════════════ Customers Section ═══════════════════════ -->
        <div id="section-customers" class="section-panel active">
            <div class="content-card">
                <div class="content-title">
                    <i class="bi bi-people"></i> รายการลูกค้า
                    <span id="custTotalCount" style="font-size:0.8rem;color:var(--gray-500);margin-left:auto;"></span>
                </div>
                <div class="d-flex gap-2 mb-3" style="flex-wrap:wrap;">
                    <input type="text" class="form-control" id="custSearch" placeholder="ค้นหาชื่อ / รหัสลูกค้า..." style="max-width:280px;" onkeyup="if(event.key==='Enter')loadCustomers()">
                    <select class="form-control" id="custInvoiceFilter" onchange="custCurrentOffset=0;loadCustomers()" style="max-width:180px;">
                        <option value="">ทุกสถานะ</option>
                        <option value="unpaid">มีค้างชำระ</option>
                        <option value="overdue">เกินกำหนด</option>
                    </select>
                    <select class="form-control" id="custSalesperson" onchange="custCurrentOffset=0;loadCustomers()" style="max-width:220px;">
                        <option value="">พนักงานขาย: ทั้งหมด</option>
                    </select>
                    <select class="form-control" id="custSortBy" onchange="custCurrentOffset=0;loadCustomers()" style="max-width:200px;">
                        <option value="">เรียงตาม: ล่าสุด</option>
                        <option value="spend_desc">ยอดซื้อ: มาก→น้อย</option>
                        <option value="spend_asc">ยอดซื้อ: น้อย→มาก</option>
                        <option value="orders_desc">ออเดอร์: มาก→น้อย</option>
                        <option value="orders_asc">ออเดอร์: น้อย→มาก</option>
                        <option value="due_desc">ค้างชำระ: มาก→น้อย</option>
                        <option value="name_asc">ชื่อ: ก→ฮ</option>
                    </select>
                    <button class="btn-primary" onclick="loadCustomers()"><i class="bi bi-search"></i> ค้นหา</button>
                    <button class="chip" onclick="resetCustomerFilter()"><i class="bi bi-x-circle"></i> ล้าง</button>
                    <button class="chip" onclick="loadCustomers()"><i class="bi bi-arrow-repeat"></i> รีเฟรช</button>
                </div>
                <div id="customerList"><div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลด...</div></div></div>
                <div id="customerPagination" class="d-flex justify-content-center gap-2 mt-3" style="display:none !important;"></div>
            </div>
        </div>

        <!-- ═══════════════════════ Daily Summary Section ═══════════════════════ -->
        <div id="section-daily-summary" class="section-panel">
            <div class="content-card mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="content-title mb-0">
                        <i class="bi bi-calendar-check"></i> สรุปออเดอร์ประจำวัน
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn-primary" onclick="loadDailySummary()"><i class="bi bi-arrow-repeat"></i> โหลดข้อมูลใหม่</button>
                        <button class="btn-primary" onclick="sendDailySummaryAll()" style="background:linear-gradient(135deg,#059669,#10b981);"><i class="bi bi-send-check"></i> ส่งแจ้งเตือนทั้งหมด</button>
                    </div>
                </div>
                <div class="mt-3 text-muted" style="font-size:0.85rem;">
                    แสดงรายการลูกค้าที่มีออเดอร์ค้างส่งหรือมีการเคลื่อนไหวในวันนี้ คุณสามารถตรวจสอบและกดส่งแจ้งเตือนสรุปสถานะให้ลูกค้าทาง LINE ได้ (ส่งได้ 1 ครั้ง/วัน/คน)
                </div>
            </div>

            <div class="content-card mb-3">
                <div class="content-title">
                    <i class="bi bi-clock-history"></i> ตั้งค่าส่งอัตโนมัติ
                </div>
                <div id="autoSendSettingsContent">
                    <div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลดการตั้งค่า...</div></div>
                </div>
            </div>

            <div class="content-card mb-3" id="autoSendHistoryCard" style="display:none;">
                <div class="content-title">
                    <i class="bi bi-list-check"></i> ประวัติการส่งอัตโนมัติ
                    <button class="chip" onclick="loadAutoSendHistory()" style="margin-left:auto;"><i class="bi bi-arrow-repeat"></i> รีเฟรช</button>
                </div>
                <div id="autoSendHistoryContent">
                    <div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลด...</div></div>
                </div>
            </div>

            <div class="content-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="fw-bold"><span id="dailySummaryCount">0</span> รายการ</div>
                    <div class="d-flex gap-2">
                        <select class="form-control form-control-sm" id="dailySummaryFilterStatus" onchange="filterDailySummaryList()" style="width:150px;">
                            <option value="all">ทั้งหมด</option>
                            <option value="pending">ยังไม่ได้ส่งวันนี้</option>
                            <option value="sent">ส่งแล้ววันนี้</option>
                        </select>
                        <input type="text" class="form-control form-control-sm" id="dailySummarySearch" placeholder="ค้นหาชื่อ..." onkeyup="filterDailySummaryList()" style="width:200px;">
                    </div>
                </div>
                <div id="dailySummaryList"><div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>คลิกโหลดข้อมูลใหม่เพื่อดูรายการ...</div></div></div>
            </div>
        </div>

        <!-- ═══════════════════════ Slips Section ═══════════════════════ -->
        <div id="section-slips" class="section-panel">
            <div class="content-card mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="content-title mb-0"><i class="bi bi-receipt"></i> สลิปการชำระเงิน</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <input type="text" class="form-control" id="slipSearch" placeholder="ค้นหาชื่อลูกค้า / LINE ID..." style="max-width:240px;" onkeyup="if(event.key==='Enter')loadSlips()">
                        <select class="form-control" id="slipStatusFilter" onchange="loadSlips()" style="max-width:160px;">
                            <option value="">ทุกสถานะ</option>
                            <option value="pending">รอตรวจสอบ</option>
                            <option value="matched">จับคู่แล้ว</option>
                            <option value="failed">ไม่สำเร็จ</option>
                        </select>
                        <input type="date" class="form-control" id="slipDateFilter" onchange="loadSlips()" style="max-width:160px;">
                        <button class="btn-primary" onclick="loadSlips()"><i class="bi bi-search"></i> ค้นหา</button>
                        <button class="chip" onclick="document.getElementById('slipSearch').value='';document.getElementById('slipStatusFilter').value='';document.getElementById('slipDateFilter').value='';loadSlips();"><i class="bi bi-x-circle"></i> ล้าง</button>
                        <button class="chip" onclick="loadSlips()"><i class="bi bi-arrow-repeat"></i> รีเฟรช</button>
                        <button class="btn-primary" id="sendAllOdooBtn" onclick="sendAllSlipsToOdoo()" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);"><i class="bi bi-cloud-upload"></i> ส่งทั้งหมดไปยัง Odoo</button>
                    </div>
                </div>
            </div>
            <div class="content-card">
                <div class="content-title">
                    <i class="bi bi-list-ul"></i> รายการสลิปทั้งหมด
                    <span id="slipTotalCount" style="font-size:0.8rem;color:var(--gray-500);margin-left:auto;"></span>
                </div>
                <div id="slipList"><div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กดรีเฟรชเพื่อโหลดข้อมูล</div></div></div>
                <div id="slipPagination" class="d-flex justify-content-center gap-2 mt-3"></div>
            </div>
        </div>

        <!-- ═══════════════════════ System Health Section ═══════════════════════ -->
        <div id="section-health" class="section-panel">
            <div class="content-card">
                <div class="content-title" style="display:flex;justify-content:space-between;align-items:center;">
                    <span><i class="bi bi-heart-pulse"></i> System Health Dashboard</span>
                    <button class="chip" onclick="loadSystemHealth()" style="font-size:0.8rem;"><i class="bi bi-arrow-repeat"></i> รีเฟรช</button>
                </div>
                <div id="healthContent">
                    <div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังตรวจสอบสุขภาพระบบ...</div></div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════ Notifications Section ═══════════════════════ -->
        <div id="section-notifications" class="section-panel">
            <div id="notifStats" class="mb-3">
                <div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลดสถิติ...</div></div>
            </div>
            <div class="content-card">
                <div class="content-title"><i class="bi bi-funnel"></i> ตัวกรอง</div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">สถานะ</label>
                            <select class="form-control" id="notifFilterStatus" onchange="loadNotifications()">
                                <option value="">ทั้งหมด</option>
                                <option value="sent">ส่งสำเร็จ</option>
                                <option value="failed">ล้มเหลว</option>
                                <option value="skipped">ข้าม</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">ประเภท Event</label>
                            <select class="form-control" id="notifFilterEvent" onchange="loadNotifications()"><option value="">ทั้งหมด</option></select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="form-label">จากวันที่</label>
                            <input type="date" class="form-control" id="notifFilterDateFrom" onchange="loadNotifications()">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="form-label">ถึงวันที่</label>
                            <input type="date" class="form-control" id="notifFilterDateTo" onchange="loadNotifications()">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2" style="padding-bottom:1rem;">
                        <button class="btn-primary" onclick="loadNotifications()"><i class="bi bi-search"></i> ค้นหา</button>
                        <button class="chip" onclick="resetNotifFilters()"><i class="bi bi-x-circle"></i> ล้าง</button>
                    </div>
                </div>
            </div>
            <div class="content-card" style="margin-top:1rem;">
                <div class="content-title">
                    <i class="bi bi-bell"></i> ประวัติการแจ้งเตือน
                    <span id="notifTotalCount" style="font-size:0.8rem;color:var(--gray-500);margin-left:auto;"></span>
                </div>
                <div id="notifList"><div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลด...</div></div></div>
                <div id="notifPagination" class="d-flex justify-content-center gap-2 mt-3" style="display:none !important;"></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ ORDER TIMELINE MODAL ═══════════════════════ -->
    <div id="orderTimelineModal" class="modal-backdrop-custom" onclick="if(event.target===this){this.classList.remove('active');}">
        <div class="detail-modal-container" style="max-width:750px;">
            <div class="detail-modal-header">
                <h5><i class="bi bi-clock-history" style="color:var(--primary);"></i> <span id="orderTimelineTitle">ไทม์ไลน์ออเดอร์</span></h5>
                <button class="detail-modal-close" onclick="closeTimelineModal()">&times;</button>
            </div>
            <div class="detail-modal-body" id="orderTimelineContent"></div>
        </div>
    </div>

    <!-- ═══════════════════════ CUSTOMER DETAIL MODAL ═══════════════════════ -->
    <div id="customerInvoiceModal" class="modal-backdrop-custom" onclick="if(event.target===this){closeCustomerInvoiceModal();}">
        <div class="detail-modal-container">
            <div class="detail-modal-header">
                <h5 id="customerInvoiceTitle"><i class="bi bi-person-lines-fill" style="color:var(--primary);"></i> ข้อมูลลูกค้า</h5>
                <button class="detail-modal-close" onclick="closeCustomerInvoiceModal()">&times;</button>
            </div>
            <div class="detail-modal-body" id="customerInvoiceContent"></div>
        </div>
    </div>

    <!-- ═══════════════════════ ORDER DETAIL MODAL ═══════════════════════ -->
    <div id="orderDetailModal" class="modal-backdrop-custom" onclick="if(event.target===this){this.classList.remove('active');}">
        <div class="detail-modal-container" style="max-width:800px;">
            <div class="detail-modal-header">
                <h5><i class="bi bi-cart3" style="color:var(--info);"></i> <span id="orderDetailTitle">รายละเอียดออเดอร์</span></h5>
                <button class="detail-modal-close" onclick="document.getElementById('orderDetailModal').classList.remove('active')">&times;</button>
            </div>
            <div class="detail-modal-body" id="orderDetailContent">
                <div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลด...</div></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ INVOICE DETAIL MODAL ═══════════════════════ -->
    <div id="invoiceDetailModal" class="modal-backdrop-custom" onclick="if(event.target===this){this.classList.remove('active');}">
        <div class="detail-modal-container" style="max-width:800px;">
            <div class="detail-modal-header">
                <h5><i class="bi bi-receipt" style="color:var(--violet);"></i> <span id="invoiceDetailTitle">รายละเอียดใบแจ้งหนี้</span></h5>
                <button class="detail-modal-close" onclick="document.getElementById('invoiceDetailModal').classList.remove('active')">&times;</button>
            </div>
            <div class="detail-modal-body" id="invoiceDetailContent">
                <div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลด...</div></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ BDO DETAIL MODAL ═══════════════════════ -->
    <div id="bdoDetailModal" class="modal-backdrop-custom" onclick="if(event.target===this){this.classList.remove('active');}">
        <div class="detail-modal-container" style="max-width:750px;">
            <div class="detail-modal-header">
                <h5><i class="bi bi-file-earmark-check" style="color:var(--success);"></i> <span id="bdoDetailTitle">รายละเอียด BDO</span></h5>
                <button class="detail-modal-close" onclick="document.getElementById('bdoDetailModal').classList.remove('active')">&times;</button>
            </div>
            <div class="detail-modal-body" id="bdoDetailContent">
                <div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลด...</div></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ PAYMENT DETAIL MODAL ═══════════════════════ -->
    <div id="paymentDetailModal" class="modal-backdrop-custom" onclick="if(event.target===this){this.classList.remove('active');}">
        <div class="detail-modal-container" style="max-width:700px;">
            <div class="detail-modal-header">
                <h5><i class="bi bi-credit-card" style="color:var(--success);"></i> <span id="paymentDetailTitle">รายละเอียดการชำระเงิน</span></h5>
                <button class="detail-modal-close" onclick="document.getElementById('paymentDetailModal').classList.remove('active')">&times;</button>
            </div>
            <div class="detail-modal-body" id="paymentDetailContent">
                <div class="loading"><i class="bi bi-arrow-repeat spin"></i><div>กำลังโหลด...</div></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ PDF VIEWER MODAL ═══════════════════════ -->
    <div id="pdfViewerModal" class="modal-backdrop-custom" onclick="if(event.target===this){closePdfViewer();}">
        <div class="detail-modal-container" style="max-width:950px;">
            <div class="detail-modal-header">
                <h5><i class="bi bi-file-earmark-pdf" style="color:#dc2626;"></i> <span id="pdfViewerTitle">ดูเอกสาร PDF</span></h5>
                <div class="d-flex gap-2 align-items-center">
                    <a id="pdfDownloadLink" href="#" target="_blank" class="chip" style="font-size:0.78rem;text-decoration:none;"><i class="bi bi-download"></i> ดาวน์โหลด</a>
                    <button class="detail-modal-close" onclick="closePdfViewer()">&times;</button>
                </div>
            </div>
            <div class="detail-modal-body" style="padding:0;">
                <div id="pdfViewerContent" class="pdf-viewer-container">
                    <div class="loading" style="color:var(--gray-400);padding:4rem;"><i class="bi bi-file-earmark-pdf" style="font-size:3rem;"></i><div>เลือกเอกสารเพื่อดู</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════ MULTI-ORDER MATCH MODAL ═══════════════════════ -->
    <div id="multiMatchModal" class="modal-backdrop-custom" onclick="if(event.target===this)this.classList.remove('active');">
        <div style="background:#fff;border-radius:var(--radius-xl);padding:0;max-width:640px;width:95%;max-height:90vh;overflow:hidden;box-shadow:var(--shadow-xl);display:flex;flex-direction:column;margin:1.5rem auto;">
            <div style="padding:20px 24px 16px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <div style="font-weight:700;font-size:1.05rem;color:var(--gray-800);"><i class="bi bi-diagram-3" style="color:var(--violet);"></i> จับคู่ออเดอร์/ใบแจ้งหนี้</div>
                    <div style="font-size:0.82rem;color:var(--gray-500);margin-top:2px;">รองรับการโอนรวมยอดหลายออเดอร์ในครั้งเดียว</div>
                </div>
                <button class="detail-modal-close" onclick="document.getElementById('multiMatchModal').classList.remove('active');">&times;</button>
            </div>
            <div style="padding:14px 24px;background:var(--gray-50);border-bottom:1px solid var(--gray-200);display:flex;gap:24px;flex-wrap:wrap;">
                <div><span style="font-size:0.75rem;color:var(--gray-500);">สลิป</span><div style="font-weight:600;font-size:0.9rem;" id="mmSlipId">-</div></div>
                <div><span style="font-size:0.75rem;color:var(--gray-500);">ลูกค้า</span><div style="font-weight:600;font-size:0.9rem;" id="mmCustomer">-</div></div>
                <div><span style="font-size:0.75rem;color:var(--gray-500);">ยอดโอน</span><div style="font-weight:700;font-size:1rem;color:#059669;" id="mmAmount">-</div></div>
            </div>
            <div style="flex:1;overflow-y:auto;padding:16px 24px;">
                <div id="mmSuggestions"></div>
                <div style="font-weight:600;font-size:0.85rem;color:var(--gray-700);margin:12px 0 8px;"><i class="bi bi-list-check"></i> เลือกออเดอร์/ใบแจ้งหนี้ที่ต้องการจับคู่</div>
                <div id="mmOrderList"><div class="loading"><i class="bi bi-arrow-repeat spin"></i> กำลังโหลด...</div></div>
                <div style="margin-top:14px;padding:12px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;">
                    <div style="font-size:0.8rem;font-weight:600;color:#0284c7;margin-bottom:6px;"><i class="bi bi-check-circle"></i> รายการที่เลือก</div>
                    <div id="mmSelected"><span style="color:var(--gray-400);">ยังไม่เลือก</span></div>
                </div>
            </div>
            <div style="padding:16px 24px;border-top:1px solid var(--gray-200);display:flex;gap:10px;justify-content:flex-end;background:var(--gray-50);">
                <button onclick="document.getElementById('multiMatchModal').classList.remove('active');" style="background:var(--gray-100);color:var(--gray-700);border:none;border-radius:var(--radius-sm);padding:9px 20px;font-size:0.875rem;cursor:pointer;font-family:inherit;">ยกเลิก</button>
                <button id="mmConfirmBtn" onclick="mmConfirmMatch()" disabled style="background:var(--violet);color:#fff;border:none;border-radius:var(--radius-sm);padding:9px 22px;font-size:0.875rem;cursor:pointer;font-weight:600;font-family:inherit;"><i class="bi bi-check2-circle"></i> ยืนยันจับคู่</button>
            </div>
        </div>
    </div>

    <script src="odoo-dashboard.js?v=<?= filemtime(__DIR__ . '/odoo-dashboard.js') ?>"></script>
</body>
</html>
