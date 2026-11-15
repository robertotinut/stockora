<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', __('app.brand_name')) &mdash; Stockora</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 12mm;
    }

    *, *::before, *::after {
      box-sizing: border-box;
    }

    html, body {
      margin: 0;
      padding: 0;
      background: #e5e7eb;
      color: #111827;
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      font-size: 12px;
      line-height: 1.45;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .stk-print-toolbar {
      position: sticky;
      top: 0;
      z-index: 50;
      background: #0f172a;
      color: #f8fafc;
      padding: 10px 20px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      border-bottom: 1px solid #1e293b;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.25);
    }

    .stk-print-toolbar-title {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .stk-print-toolbar-title strong {
      font-size: 13px;
      letter-spacing: 0.01em;
    }

    .stk-print-toolbar-title span {
      font-size: 11px;
      color: #94a3b8;
    }

    .stk-print-toolbar-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .stk-print-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      border: 1px solid transparent;
    }

    .stk-print-btn-secondary {
      background: #1e293b;
      color: #e2e8f0;
      border-color: #334155;
    }

    .stk-print-btn-secondary:hover {
      background: #334155;
      color: #fff;
    }

    .stk-print-btn-primary {
      background: #4f46e5;
      color: #ffffff;
    }

    .stk-print-btn-primary:hover {
      background: #4338ca;
    }

    .stk-print-page {
      width: 210mm;
      max-width: calc(100% - 24px);
      min-height: 273mm;
      margin: 20px auto 32px;
      background: #ffffff;
      padding: 14mm 15mm;
      border: 1px solid #d1d5db;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.10);
    }

    .stk-doc-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
      padding-bottom: 14px;
      border-bottom: 2px solid #111827;
      margin-bottom: 16px;
    }

    .stk-doc-company {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      flex: 1 1 auto;
    }

    .stk-doc-logo {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      object-fit: cover;
      border: 1px solid #e5e7eb;
      flex-shrink: 0;
    }

    .stk-doc-logo-fallback {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      background: #111827;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 16px;
      flex-shrink: 0;
    }

    .stk-doc-company-name {
      margin: 0 0 2px;
      font-size: 16px;
      font-weight: 700;
      color: #111827;
    }

    .stk-doc-company-meta {
      font-size: 11px;
      color: #4b5563;
      line-height: 1.45;
    }

    .stk-doc-identity {
      text-align: right;
      flex: 0 0 auto;
      min-width: 215px;
    }

    .stk-doc-title {
      font-size: 15px;
      font-weight: 800;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #111827;
      margin: 0 0 6px;
    }

    .stk-doc-number-box {
      display: inline-block;
      padding: 4px 10px;
      border: 1.5px solid #111827;
      border-radius: 4px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 13px;
      font-weight: 700;
      color: #111827;
      background: #f9fafb;
    }

    .stk-doc-status-line {
      margin-top: 6px;
      font-size: 11px;
      color: #374151;
    }

    .stk-doc-badge {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 3px;
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      border: 1px solid #9ca3af;
      background: #f3f4f6;
      color: #111827;
    }

    .stk-doc-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
      margin-bottom: 16px;
    }

    .stk-doc-box {
      border: 1px solid #d1d5db;
      border-radius: 4px;
      padding: 10px 12px;
      background: #ffffff;
    }

    .stk-doc-box-title {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #4b5563;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 4px;
      margin-bottom: 7px;
    }

    .stk-kv-table {
      width: 100%;
      border-collapse: collapse;
    }

    .stk-kv-table td {
      padding: 2px 0;
      vertical-align: top;
      font-size: 11.5px;
    }

    .stk-kv-table td.stk-kv-label {
      color: #4b5563;
      width: 38%;
      padding-right: 8px;
    }

    .stk-kv-table td.stk-kv-val {
      color: #111827;
      font-weight: 500;
      word-break: break-word;
    }

    .stk-items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
    }

    .stk-items-table thead {
      display: table-header-group;
    }

    .stk-items-table tfoot {
      display: table-footer-group;
    }

    .stk-items-table th {
      background: #f3f4f6;
      color: #111827;
      font-size: 10.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      border: 1px solid #9ca3af;
      padding: 6px 7px;
      text-align: left;
    }

    .stk-items-table td {
      border: 1px solid #d1d5db;
      padding: 6px 7px;
      vertical-align: top;
      font-size: 11.5px;
    }

    .stk-items-table tr {
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .text-right { text-align: right !important; }
    .text-center { text-align: center !important; }
    .tx-numeric {
      font-variant-numeric: tabular-nums;
      white-space: nowrap;
    }
    .tx-mono {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .stk-doc-summary-grid {
      display: grid;
      grid-template-columns: 1.25fr 1fr;
      gap: 14px;
      margin-bottom: 18px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .stk-totals-table {
      width: 100%;
      border-collapse: collapse;
    }

    .stk-totals-table td {
      padding: 4px 8px;
      border-bottom: 1px solid #e5e7eb;
      font-size: 11.5px;
    }

    .stk-totals-table tr.stk-total-grand td {
      border-top: 2px solid #111827;
      border-bottom: 2px solid #111827;
      background: #f3f4f6;
      font-weight: 700;
      font-size: 12.5px;
      padding: 6px 8px;
    }

    .stk-doc-alert {
      border: 1px solid #d97706;
      background: #fffbeb;
      color: #92400e;
      padding: 8px 10px;
      border-radius: 4px;
      font-size: 11.5px;
      font-weight: 600;
      margin-bottom: 12px;
      break-inside: avoid;
    }

    .stk-signature-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
      margin-top: 22px;
      break-inside: avoid;
      page-break-inside: avoid;
    }

    .stk-signature-box {
      border: 1px solid #d1d5db;
      border-radius: 4px;
      padding: 8px 10px;
      text-align: center;
      min-height: 92px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .stk-signature-role {
      font-size: 10.5px;
      font-weight: 700;
      text-transform: uppercase;
      color: #374151;
    }

    .stk-signature-line {
      border-top: 1px solid #6b7280;
      padding-top: 4px;
      font-size: 10.5px;
      color: #4b5563;
      margin-top: 42px;
    }

    .stk-print-footer {
      margin-top: 18px;
      padding-top: 8px;
      border-top: 1px solid #e5e7eb;
      display: flex;
      justify-content: space-between;
      font-size: 10px;
      color: #6b7280;
      break-inside: avoid;
    }

    @media (max-width: 720px) {
      .stk-doc-header {
        flex-direction: column;
      }
      .stk-doc-identity {
        text-align: left;
      }
      .stk-doc-grid,
      .stk-doc-summary-grid,
      .stk-signature-grid {
        grid-template-columns: 1fr;
      }
      .stk-print-page {
        padding: 16px;
      }
    }

    @media print {
      html, body {
        background: #ffffff !important;
      }
      .no-print,
      .stk-print-toolbar {
        display: none !important;
      }
      .stk-print-page {
        width: 100%;
        max-width: none;
        min-height: auto;
        margin: 0;
        padding: 0;
        border: none;
        box-shadow: none;
      }
    }
  </style>
</head>
<body>
  <div class="stk-print-toolbar no-print">
    <div class="stk-print-toolbar-title">
      <strong>@yield('title', __('app.brand_name'))</strong>
      <span>{{ __('app.print_hint') }}</span>
    </div>
    <div class="stk-print-toolbar-actions">
      <a href="@yield('back_url', route('dashboard'))" class="stk-print-btn stk-print-btn-secondary">
        &larr; {{ __('app.back') }}
      </a>
      <button type="button" onclick="window.print()" class="stk-print-btn stk-print-btn-primary">
        {{ __('app.print_now') }}
      </button>
    </div>
  </div>

  <main class="stk-print-page">
    <header class="stk-doc-header">
      <div class="stk-doc-company">
        @php($printCompany = $company ?? ($activeCompany ?? null))
        @if($printCompany && $printCompany->logoUrl())
          <img src="{{ $printCompany->logoUrl() }}" alt="{{ $printCompany->name }}" class="stk-doc-logo">
        @elseif($printCompany)
          <div class="stk-doc-logo-fallback">{{ $printCompany->initials() }}</div>
        @endif
        <div>
          <h1 class="stk-doc-company-name">{{ $printCompany->name ?? __('app.brand_name') }}</h1>
          <div class="stk-doc-company-meta">
            @if($printCompany && $printCompany->code)
              <div><strong>{{ __('app.code') }}:</strong> {{ $printCompany->code }}</div>
            @endif
            @if($printCompany && $printCompany->address)
              <div>{{ $printCompany->address }}</div>
            @endif
            @if($printCompany && $printCompany->phone)
              <div>{{ __('app.phone') }}: {{ $printCompany->phone }}</div>
            @endif
          </div>
        </div>
      </div>

      <div class="stk-doc-identity">
        <div class="stk-doc-title">@yield('doc_heading')</div>
        <div class="stk-doc-number-box">@yield('doc_number')</div>
        <div class="stk-doc-status-line">
          @yield('doc_status_meta')
        </div>
      </div>
    </header>

    @yield('print_content')

    <footer class="stk-print-footer">
      <div>{{ __('app.brand_name') }} &mdash; {{ __('app.tagline') }}</div>
      <div>
        {{ __('app.printed_by') }}: {{ Auth::user()->name ?? __('app.system') }}
        &bull; {{ __('app.printed_at') }}: {{ \App\Support\StockoraFormat::dateTime(now()) }}
      </div>
    </footer>
  </main>
</body>
</html>
