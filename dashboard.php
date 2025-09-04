<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Variables for Theme */
        :root {
            /* Color Palette */
            --primary-50: #f8f5e8;
            --primary-100: #f5f0d9;
            --primary-200: #f5e6b3;
            --primary-300: #f5d97d;
            --primary-400: #f5cc47;
            --primary-500: #f5bf11;
            --primary-600: #d9a90f;
            --primary-700: #a3810b;
            --primary-800: #6d5808;
            --primary-900: #372c04;
            
            --secondary-50: #f5f9f0;
            --secondary-100: #e8f5e9;
            --secondary-200: #c8e6c9;
            --secondary-300: #a5d6a7;
            --secondary-400: #81c784;
            --secondary-500: #4caf50;
            --secondary-600: #43a047;
            --secondary-700: #388e3c;
            --secondary-800: #2e7d32;
            --secondary-900: #1b5e20;
            
            --neutral-50: #fafafa;
            --neutral-100: #f5f5f5;
            --neutral-200: #eeeeee;
            --neutral-300: #e0e0e0;
            --neutral-400: #bdbdbd;
            --neutral-500: #9e9e9e;
            --neutral-600: #757575;
            --neutral-700: #616161;
            --neutral-800: #424242;
            --neutral-900: #212121;
            
            --error-50: #ffebee;
            --error-100: #ffcdd2;
            --error-200: #ef9a9a;
            --error-300: #e57373;
            --error-400: #ef5350;
            --error-500: #f44336;
            --error-600: #e53935;
            --error-700: #d32f2f;
            --error-800: #c62828;
            --error-900: #b71c1c;
            
            --warning-50: #fff8e1;
            --warning-100: #ffecb3;
            --warning-200: #ffe082;
            --warning-300: #ffd54f;
            --warning-400: #ffca28;
            --warning-500: #ffc107;
            --warning-600: #ffb300;
            --warning-700: #ffa000;
            --warning-800: #ff8f00;
            --warning-900: #ff6f00;
            
            --success-50: #e8f5e9;
            --success-100: #c8e6c9;
            --success-200: #a5d6a7;
            --success-300: #81c784;
            --success-400: #66bb6a;
            --success-500: #4caf50;
            --success-600: #43a047;
            --success-700: #388e3c;
            --success-800: #2e7d32;
            --success-900: #1b5e20;
            
            /* Spacing */
            --space-xxs: 0.25rem; /* 4px */
            --space-xs: 0.5rem;  /* 8px */
            --space-sm: 0.75rem; /* 12px */
            --space-md: 1rem;    /* 16px */
            --space-lg: 1.5rem;   /* 24px */
            --space-xl: 2rem;    /* 32px */
            --space-xxl: 3rem;   /* 48px */
            
            /* Typography */
            --text-xs: 0.75rem;   /* 12px */
            --text-sm: 0.875rem;  /* 14px */
            --text-base: 1rem;    /* 16px */
            --text-md: 1.125rem;  /* 18px */
            --text-lg: 1.25rem;   /* 20px */
            --text-xl: 1.5rem;    /* 24px */
            --text-xxl: 2rem;     /* 32px */
            --text-xxxl: 2.5rem;   /* 40px */
            
            /* Border Radius */
            --radius-sm: 0.25rem;  /* 4px */
            --radius-md: 0.5rem;  /* 8px */
            --radius-lg: 0.75rem; /* 12px */
            --radius-xl: 1rem;     /* 16px */
            --radius-full: 9999px;
            
            /* Shadows */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --shadow-inset: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05);
            
            /* Transitions */
            --transition-fast: 150ms ease-in-out;
            --transition-normal: 300ms ease-in-out;
            --transition-slow: 500ms ease-in-out;
            
            /* Z-index */
            --z-index-dropdown: 100;
            --z-index-sticky: 200;
            --z-index-fixed: 300;
            --z-index-modal: 400;
            --z-index-toast: 500;
        }
        
        /* Base Styles */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        html {
            scroll-behavior: smooth;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.5;
            color: var(--neutral-800);
            background-color: var(--primary-50);
        }
        
        img {
            max-width: 100%;
            display: block;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        button {
            cursor: pointer;
            font-family: inherit;
            border: none;
            background: none;
        }
        
        ul, ol {
            list-style: none;
        }
        
        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            line-height: 1.2;
            color: var(--neutral-900);
        }
        
        h1 { font-size: var(--text-xxxl); }
        h2 { font-size: var(--text-xxl); }
        h3 { font-size: var(--text-xl); }
        h4 { font-size: var(--text-lg); }
        h5 { font-size: var(--text-md); }
        h6 { font-size: var(--text-base); }
        
        .text-xs { font-size: var(--text-xs); }
        .text-sm { font-size: var(--text-sm); }
        .text-base { font-size: var(--text-base); }
        .text-md { font-size: var(--text-md); }
        .text-lg { font-size: var(--text-lg); }
        .text-xl { font-size: var(--text-xl); }
        .text-xxl { font-size: var(--text-xxl); }
        
        .font-light { font-weight: 300; }
        .font-normal { font-weight: 400; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        
        /* Layout */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }
        
        .flex {
            display: flex;
        }
        
        .flex-col {
            flex-direction: column;
        }
        
        .items-center {
            align-items: center;
        }
        
        .items-start {
            align-items: flex-start;
        }
        
        .items-end {
            align-items: flex-end;
        }
        
        .justify-center {
            justify-content: center;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        .justify-start {
            justify-content: flex-start;
        }
        
        .justify-end {
            justify-content: flex-end;
        }
        
        .gap-xxs { gap: var(--space-xxs); }
        .gap-xs { gap: var(--space-xs); }
        .gap-sm { gap: var(--space-sm); }
        .gap-md { gap: var(--space-md); }
        .gap-lg { gap: var(--space-lg); }
        .gap-xl { gap: var(--space-xl); }
        .gap-xxl { gap: var(--space-xxl); }
        
        .grid {
            display: grid;
        }
        
        .grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        
        .grid-cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        
        .grid-cols-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        
        .grid-cols-5 {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
        
        /* Spacing */
        .p-xxs { padding: var(--space-xxs); }
        .p-xs { padding: var(--space-xs); }
        .p-sm { padding: var(--space-sm); }
        .p-md { padding: var(--space-md); }
        .p-lg { padding: var(--space-lg); }
        .p-xl { padding: var(--space-xl); }
        .p-xxl { padding: var(--space-xxl); }
        
        .px-xxs { padding-left: var(--space-xxs); padding-right: var(--space-xxs); }
        .px-xs { padding-left: var(--space-xs); padding-right: var(--space-xs); }
        .px-sm { padding-left: var(--space-sm); padding-right: var(--space-sm); }
        .px-md { padding-left: var(--space-md); padding-right: var(--space-md); }
        .px-lg { padding-left: var(--space-lg); padding-right: var(--space-lg); }
        .px-xl { padding-left: var(--space-xl); padding-right: var(--space-xl); }
        .px-xxl { padding-left: var(--space-xxl); padding-right: var(--space-xxl); }
        
        .py-xxs { padding-top: var(--space-xxs); padding-bottom: var(--space-xxs); }
        .py-xs { padding-top: var(--space-xs); padding-bottom: var(--space-xs); }
        .py-sm { padding-top: var(--space-sm); padding-bottom: var(--space-sm); }
        .py-md { padding-top: var(--space-md); padding-bottom: var(--space-md); }
        .py-lg { padding-top: var(--space-lg); padding-bottom: var(--space-lg); }
        .py-xl { padding-top: var(--space-xl); padding-bottom: var(--space-xl); }
        .py-xxl { padding-top: var(--space-xxl); padding-bottom: var(--space-xxl); }
        
        .m-xxs { margin: var(--space-xxs); }
        .m-xs { margin: var(--space-xs); }
        .m-sm { margin: var(--space-sm); }
        .m-md { margin: var(--space-md); }
        .m-lg { margin: var(--space-lg); }
        .m-xl { margin: var(--space-xl); }
        .m-xxl { margin: var(--space-xxl); }
        
        .mx-auto { margin-left: auto; margin-right: auto; }
        
        /* Width & Height */
        .w-full { width: 100%; }
        .h-full { height: 100%; }
        
        /* Background Colors */
        .bg-primary-50 { background-color: var(--primary-50); }
        .bg-primary-100 { background-color: var(--primary-100); }
        .bg-primary-200 { background-color: var(--primary-200); }
        .bg-primary-300 { background-color: var(--primary-300); }
        .bg-primary-400 { background-color: var(--primary-400); }
        .bg-primary-500 { background-color: var(--primary-500); }
        
        .bg-secondary-50 { background-color: var(--secondary-50); }
        .bg-secondary-100 { background-color: var(--secondary-100); }
        .bg-secondary-200 { background-color: var(--secondary-200); }
        .bg-secondary-300 { background-color: var(--secondary-300); }
        .bg-secondary-400 { background-color: var(--secondary-400); }
        .bg-secondary-500 { background-color: var(--secondary-500); }
        
        .bg-neutral-50 { background-color: var(--neutral-50); }
        .bg-neutral-100 { background-color: var(--neutral-100); }
        .bg-neutral-200 { background-color: var(--neutral-200); }
        .bg-neutral-300 { background-color: var(--neutral-300); }
        .bg-neutral-400 { background-color: var(--neutral-400); }
        .bg-neutral-500 { background-color: var(--neutral-500); }
        
        .bg-white { background-color: white; }
        
        /* Text Colors */
        .text-primary-500 { color: var(--primary-500); }
        .text-primary-600 { color: var(--primary-600); }
        .text-primary-700 { color: var(--primary-700); }
        
        .text-secondary-500 { color: var(--secondary-500); }
        .text-secondary-600 { color: var(--secondary-600); }
        .text-secondary-700 { color: var(--secondary-700); }
        
        .text-neutral-500 { color: var(--neutral-500); }
        .text-neutral-600 { color: var(--neutral-600); }
        .text-neutral-700 { color: var(--neutral-700); }
        .text-neutral-800 { color: var(--neutral-800); }
        .text-neutral-900 { color: var(--neutral-900); }
        
        .text-white { color: white; }
        
        /* Borders */
        .border {
            border: 1px solid var(--neutral-200);
        }
        
        .border-t {
            border-top: 1px solid var(--neutral-200);
        }
        
        .border-b {
            border-bottom: 1px solid var(--neutral-200);
        }
        
        .border-l {
            border-left: 1px solid var(--neutral-200);
        }
        
        .border-r {
            border-right: 1px solid var(--neutral-200);
        }
        
        .border-primary-200 {
            border-color: var(--primary-200);
        }
        
        .border-secondary-200 {
            border-color: var(--secondary-200);
        }
        
        /* Border Radius */
        .rounded-sm { border-radius: var(--radius-sm); }
        .rounded-md { border-radius: var(--radius-md); }
        .rounded-lg { border-radius: var(--radius-lg); }
        .rounded-xl { border-radius: var(--radius-xl); }
        .rounded-full { border-radius: var(--radius-full); }
        
        /* Shadows */
        .shadow-xs { box-shadow: var(--shadow-xs); }
        .shadow-sm { box-shadow: var(--shadow-sm); }
        .shadow-md { box-shadow: var(--shadow-md); }
        .shadow-lg { box-shadow: var(--shadow-lg); }
        .shadow-xl { box-shadow: var(--shadow-xl); }
        .shadow-none { box-shadow: none; }
        
        /* Utility Classes */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
        
        .sticky {
            position: sticky;
        }
        
        .top-0 {
            top: 0;
        }
        
        .z-100 {
            z-index: 100;
        }
        
        .z-200 {
            z-index: 200;
        }
        
        .z-300 {
            z-index: 300;
        }
        
        .z-400 {
            z-index: 400;
        }
        
        .z-500 {
            z-index: 500;
        }
        
        /* Header Styles */
        .header {
            position: sticky;
            top: 0;
            z-index: var(--z-index-sticky);
            background-color: white;
            box-shadow: var(--shadow-sm);
            padding: var(--space-md) 0;
        }
        
        .header__logo {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: var(--text-lg);
            font-weight: 600;
            color: var(--secondary-600);
        }
        
        .header__logo-icon {
            color: var(--secondary-600);
            font-size: var(--text-xl);
        }
        
        .header__welcome {
            font-weight: 500;
            color: var(--secondary-600);
            white-space: nowrap;
        }
        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            font-weight: 500;
            transition: var(--transition-fast);
            white-space: nowrap;
        }
        
        .btn--primary {
            background-color: var(--secondary-500);
            color: white;
        }
        
        .btn--primary:hover {
            background-color: var(--secondary-600);
            box-shadow: var(--shadow-sm);
        }
        
        .btn--secondary {
            background-color: var(--primary-200);
            color: var(--neutral-800);
        }
        
        .btn--secondary:hover {
            background-color: var(--primary-300);
            box-shadow: var(--shadow-sm);
        }
        
        .btn--outline {
            background-color: transparent;
            color: var(--secondary-600);
            border: 1px solid var(--secondary-300);
        }
        
        .btn--outline:hover {
            background-color: var(--secondary-50);
        }
        
        /* Dashboard Layout */
        .dashboard {
            display: grid;
            grid-template-columns: minmax(250px, 300px) 1fr;
            gap: var(--space-lg);
            padding: var(--space-lg) 0;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: sticky;
            top: calc(60px + var(--space-lg));
            align-self: start;
            height: calc(100vh - 120px);
            overflow-y: auto;
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .sidebar__user {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: var(--space-lg);
            border-bottom: 1px solid var(--neutral-200);
        }
        
        .sidebar__avatar {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-full);
            background-color: var(--primary-200);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--space-sm);
            color: var(--secondary-600);
            font-weight: 600;
            font-size: var(--text-xl);
            box-shadow: var(--shadow-sm);
        }
        
        .sidebar__name {
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--secondary-600);
            margin-bottom: var(--space-xs);
        }
        
        .sidebar__role {
            font-size: var(--text-sm);
            color: var(--secondary-600);
            background-color: var(--secondary-100);
            padding: var(--space-xxs) var(--space-sm);
            border-radius: var(--radius-full);
        }
        
        .sidebar__title {
            font-size: var(--text-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--neutral-500);
            margin: var(--space-md) 0 var(--space-sm) var(--space-sm);
        }
        
        .sidebar__menu {
            display: flex;
            flex-direction: column;
            gap: var(--space-xxs);
            padding: 0 var(--space-sm);
        }
        
        .sidebar__link {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            transition: var(--transition-fast);
            font-weight: 500;
        }
        
        .sidebar__link:hover {
            background-color: var(--primary-100);
            color: var(--secondary-600);
        }
        
        .sidebar__link--active {
            background-color: var(--secondary-50);
            color: var(--secondary-600);
            font-weight: 600;
        }
        
        .sidebar__link--logout {
            color: var(--error-500);
        }
        
        .sidebar__link--logout:hover {
            background-color: var(--error-50);
        }
        
        .sidebar__icon {
            width: 20px;
            display: flex;
            justify-content: center;
            font-size: var(--text-md);
        }
        
        /* Main Content Styles */
        .main {
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--space-xl);
        }
        
        .main__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
            padding-bottom: var(--space-md);
            border-bottom: 1px solid var(--neutral-200);
        }
        
        /* Card Styles */
        .card {
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            transition: var(--transition-normal);
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .card--highlight {
            background-color: var(--secondary-50);
            border-left: 4px solid var(--secondary-500);
        }
        
        .card--primary {
            background-color: var(--primary-100);
        }
        
        .card__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-md);
        }
        
        /* Info Item */
        .info {
            display: flex;
            flex-direction: column;
            gap: var(--space-xxs);
        }
        
        .info__label {
            font-size: var(--text-sm);
            color: var(--neutral-500);
        }
        
        .info__value {
            font-size: var(--text-md);
            font-weight: 600;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            gap: var(--space-md);
            margin-top: var(--space-xl);
        }
        
        .action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: var(--space-lg);
            background-color: var(--primary-100);
            border-radius: var(--radius-lg);
            transition: var(--transition-normal);
            cursor: pointer;
        }
        
        .action:hover {
            background-color: var(--primary-200);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .action__icon {
            font-size: var(--text-xxl);
            margin-bottom: var(--space-sm);
            color: var(--secondary-600);
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: var(--space-xxs) var(--space-sm);
            font-size: var(--text-xs);
            font-weight: 600;
            border-radius: var(--radius-full);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge--success {
            background-color: var(--secondary-200);
            color: var(--secondary-800);
        }
        
        .badge--warning {
            background-color: var(--primary-200);
            color: var(--neutral-800);
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: var(--z-index-modal);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition-normal);
        }
        
        .modal--active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal__content {
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: var(--transition-normal);
        }
        
        .modal--active .modal__content {
            transform: translateY(0);
        }
        
        .modal__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-md) var(--space-lg);
            border-bottom: 1px solid var(--neutral-200);
        }
        
        .modal__close {
            font-size: var(--text-lg);
            color: var(--neutral-500);
            cursor: pointer;
            transition: var(--transition-fast);
        }
        
        .modal__close:hover {
            color: var(--neutral-700);
        }
        
        .modal__body {
            padding: var(--space-lg);
        }
        
        .modal__footer {
            display: flex;
            justify-content: flex-end;
            gap: var(--space-sm);
            padding: var(--space-md) var(--space-lg);
            border-top: 1px solid var(--neutral-200);
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: var(--space-md);
        }
        
        .form-label {
            display: block;
            margin-bottom: var(--space-xs);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: var(--space-sm);
            border: 1px solid var(--neutral-300);
            border-radius: var(--radius-sm);
            transition: var(--transition-fast);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary-400);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        /* Responsive Styles */
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 240px 1fr;
                gap: var(--space-md);
            }
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
                gap: var(--space-md);
            }
            
            .sidebar {
                position: static;
                height: auto;
                margin-bottom: var(--space-md);
            }
            
            .header__container {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-sm);
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 0 var(--space-sm);
            }
            
            .main {
                padding: var(--space-md);
            }
            
            .grid-cols-2,
            .grid-cols-3,
            .grid-cols-4 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container flex justify-between items-center">
            <a href="#" class="header__logo">
                <span class="header__logo-icon">🌱</span>
                <span>Student Portal</span>
            </a>
            <div class="flex items-center gap-md">
                <span class="header__welcome">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></span>
                <a href="logout.php" class="btn btn--secondary">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </header>
    
    <!-- Dashboard -->
    <div class="container">
        <div class="dashboard">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar__user">
                    <div class="sidebar__avatar">
                        <?= strtoupper(substr(htmlspecialchars($_SESSION['name']), 0, 1)) ?>
                    </div>
                    <h3 class="sidebar__name"><?= htmlspecialchars($_SESSION['name']) ?></h3>
                    <span class="sidebar__role">Student</span>
                </div>
                
                <h4 class="sidebar__title">Main Menu</h4>
                <ul class="sidebar__menu">
                    <li>
                        <a href="#" class="sidebar__link sidebar__link--active">
                            <span class="sidebar__icon"><i class="fas fa-user"></i></span>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="sidebar__link">
                            <span class="sidebar__icon"><i class="fas fa-store"></i></span>
                            <span>Products</span>
                        </a>
                    </li>
                    <li>
                        <a href="view_cart.php" class="sidebar__link">
                            <span class="sidebar__icon"><i class="fas fa-shopping-cart"></i></span>
                            <span>View Cart</span>
                        </a>
                    </li>
                    <li>
                        <a href="order_list.php" class="sidebar__link">
                            <span class="sidebar__icon"><i class="fas fa-clipboard-list"></i></span>
                            <span>Order List</span>
                        </a>
                    </li>
                </ul>
                
                <h4 class="sidebar__title">Account</h4>
                <ul class="sidebar__menu">
                    <li>
                        <a href="settings.php" class="sidebar__link">
                            <span class="sidebar__icon"><i class="fas fa-cog"></i></span>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="sidebar__link sidebar__link--logout">
                            <span class="sidebar__icon"><i class="fas fa-sign-out-alt"></i></span>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </aside>
            
            <!-- Main Content -->
            <main class="main">
                <div class="main__header">
                    <h1>Dashboard Overview</h1>
                    <div class="flex gap-sm">
                        <button class="btn btn--outline" id="helpBtn">
                            <i class="fas fa-question-circle"></i>
                            <span>Help</span>
                        </button>
                    </div>
                </div>
                
                <!-- Student Info Card -->
                <div class="card card--highlight">
                    <div class="grid grid-cols-3 gap-md">
                        <div class="info">
                            <span class="info__label">Student ID</span>
                            <span class="info__value"><?= htmlspecialchars($_SESSION['student_id']) ?></span>
                        </div>
                        <div class="info">
                            <span class="info__label">Department</span>
                            <span class="info__value">Computer Science</span>
                        </div>
                        <div class="info">
                            <span class="info__label">Status</span>
                            <span class="info__value">
                                <span class="badge badge--success">Active</span>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card__header">
                        <h2>Quick Actions</h2>
                        <button class="btn btn--outline">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-4 gap-md quick-actions">
                        <div class="action" onclick="showModal('coursesModal')">
                            <div class="action__icon"><i class="fas fa-book-open"></i></div>
                            <div class="font-medium">View Courses</div>
                        </div>
                        <div class="action" onclick="showModal('assignmentModal')">
                            <div class="action__icon"><i class="fas fa-pen-fancy"></i></div>
                            <div class="font-medium">Submit Assignment</div>
                        </div>
                        <div class="action" onclick="showModal('paymentModal')">
                            <div class="action__icon"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="font-medium">Pay Fees</div>
                        </div>
                        <div class="action" onclick="showModal('calendarModal')">
                            <div class="action__icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="font-medium">View Calendar</div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="card">
                    <div class="card__header">
                        <h2>Recent Activities</h2>
                        <button class="btn btn--outline">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    
                    <div class="info">
                        <div class="info__value text-center py-xl">
                            <i class="fas fa-inbox text-neutral-300" style="font-size: 3rem; margin-bottom: var(--space-sm);"></i>
                            <p class="text-neutral-500">No recent activities</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal Templates -->
    <!-- Courses Modal -->
    <div class="modal" id="coursesModal">
        <div class="modal__content">
            <div class="modal__header">
                <h3>Your Courses</h3>
                <span class="modal__close" onclick="hideModal('coursesModal')">&times;</span>
            </div>
            <div class="modal__body">
                <p>Here are your current courses:</p>
                <ul class="mt-md" style="list-style-type: none;">
                    <li class="py-sm border-b border-neutral-200">
                        <strong>CS101</strong> - Introduction to Programming
                    </li>
                    <li class="py-sm border-b border-neutral-200">
                        <strong>CS201</strong> - Data Structures
                    </li>
                    <li class="py-sm border-b border-neutral-200">
                        <strong>MATH202</strong> - Discrete Mathematics
                    </li>
                    <li class="py-sm">
                        <strong>ENG101</strong> - Academic Writing
                    </li>
                </ul>
            </div>
            <div class="modal__footer">
                <button class="btn btn--primary" onclick="hideModal('coursesModal')">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Assignment Modal -->
    <div class="modal" id="assignmentModal">
        <div class="modal__content">
            <div class="modal__header">
                <h3>Submit Assignment</h3>
                <span class="modal__close" onclick="hideModal('assignmentModal')">&times;</span>
            </div>
            <div class="modal__body">
                <form>
                    <div class="form-group">
                        <label class="form-label">Select Course</label>
                        <select class="form-control">
                            <option>CS101 - Introduction to Programming</option>
                            <option>CS201 - Data Structures</option>
                            <option>MATH202 - Discrete Mathematics</option>
                            <option>ENG101 - Academic Writing</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Assignment Title</label>
                        <input type="text" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Upload File</label>
                        <div class="border-2 border-dashed border-neutral-300 rounded-md p-lg text-center cursor-pointer">
                            <i class="fas fa-cloud-upload-alt text-2xl text-neutral-400 mb-sm"></i>
                            <p class="text-neutral-500">Drag and drop files here or click to browse</p>
                            <input type="file" class="hidden">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal__footer">
                <button class="btn btn--outline" onclick="hideModal('assignmentModal')">Cancel</button>
                <button class="btn btn--primary">Submit Assignment</button>
            </div>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div class="modal" id="paymentModal">
        <div class="modal__content">
            <div class="modal__header">
                <h3>Pay Fees</h3>
                <span class="modal__close" onclick="hideModal('paymentModal')">&times;</span>
            </div>
            <div class="modal__body">
                <div class="bg-primary-100 p-md rounded-md mb-md">
                    <h4 class="mb-sm">Outstanding Balance: $1,250.00</h4>
                    <p class="text-sm">Due Date: August 15, 2023</p>
                </div>
                
                <form>
                    <div class="form-group">
                        <label class="form-label">Payment Amount ($)</label>
                        <input type="number" value="1250.00" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <select class="form-control">
                            <option>Credit/Debit Card</option>
                            <option>Bank Transfer</option>
                            <option>PayPal</option>
                        </select>
                    </div>
                    
                    <div class="bg-secondary-50 p-md rounded-md">
                        <p class="text-sm">Your payment will be processed securely. A receipt will be emailed to you after successful payment.</p>
                    </div>
                </form>
            </div>
            <div class="modal__footer">
                <button class="btn btn--outline" onclick="hideModal('paymentModal')">Cancel</button>
                <button class="btn btn--primary">Proceed to Payment</button>
            </div>
        </div>
    </div>
    
    <!-- Calendar Modal -->
    <div class="modal" id="calendarModal">
        <div class="modal__content">
            <div class="modal__header">
                <h3>Academic Calendar</h3>
                <span class="modal__close" onclick="hideModal('calendarModal')">&times;</span>
            </div>
            <div class="modal__body">
                <div class="grid grid-cols-7 gap-sm mb-md">
                    <div class="text-center font-semibold p-xs">Sun</div>
                    <div class="text-center font-semibold p-xs">Mon</div>
                    <div class="text-center font-semibold p-xs">Tue</div>
                    <div class="text-center font-semibold p-xs">Wed</div>
                    <div class="text-center font-semibold p-xs">Thu</div>
                    <div class="text-center font-semibold p-xs">Fri</div>
                    <div class="text-center font-semibold p-xs">Sat</div>
                    
                    <!-- Calendar days -->
                    <div class="text-center p-xs">30</div>
                    <div class="text-center p-xs">31</div>
                    <div class="text-center p-xs">1</div>
                    <div class="text-center p-xs">2</div>
                    <div class="text-center p-xs">3</div>
                    <div class="text-center p-xs">4</div>
                    <div class="text-center p-xs">5</div>
                    
                    <div class="text-center p-xs">6</div>
                    <div class="text-center p-xs">7</div>
                    <div class="text-center p-xs bg-primary-200 rounded-full">8</div>
                    <div class="text-center p-xs">9</div>
                    <div class="text-center p-xs">10</div>
                    <div class="text-center p-xs">11</div>
                    <div class="text-center p-xs">12</div>
                    
                    <div class="text-center p-xs">13</div>
                    <div class="text-center p-xs">14</div>
                    <div class="text-center p-xs bg-secondary-200 rounded-full">15</div>
                    <div class="text-center p-xs">16</div>
                    <div class="text-center p-xs">17</div>
                    <div class="text-center p-xs">18</div>
                    <div class="text-center p-xs">19</div>
                    
                    <div class="text-center p-xs">20</div>
                    <div class="text-center p-xs">21</div>
                    <div class="text-center p-xs">22</div>
                    <div class="text-center p-xs">23</div>
                    <div class="text-center p-xs">24</div>
                    <div class="text-center p-xs">25</div>
                    <div class="text-center p-xs">26</div>
                    
                    <div class="text-center p-xs">27</div>
                    <div class="text-center p-xs">28</div>
                    <div class="text-center p-xs">29</div>
                    <div class="text-center p-xs">30</div>
                    <div class="text-center p-xs">31</div>
                    <div class="text-center p-xs">1</div>
                    <div class="text-center p-xs">2</div>
                </div>
                
                <div class="mt-xl">
                    <h4 class="mb-md">Upcoming Events</h4>
                    <ul style="list-style-type: none;">
                        <li class="py-sm border-b border-neutral-200 flex items-center gap-sm">
                            <div class="w-3 h-3 bg-primary-200 rounded-full"></div>
                            <div>
                                <strong>Aug 8</strong> - Semester Begins
                            </div>
                        </li>
                        <li class="py-sm border-b border-neutral-200 flex items-center gap-sm">
                            <div class="w-3 h-3 bg-secondary-200 rounded-full"></div>
                            <div>
                                <strong>Aug 15</strong> - Tuition Due
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="modal__footer">
                <button class="btn btn--primary" onclick="hideModal('calendarModal')">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="modal" id="helpModal">
        <div class="modal__content">
            <div class="modal__header">
                <h3>Help Center</h3>
                <span class="modal__close" onclick="hideModal('helpModal')">&times;</span>
            </div>
            <div class="modal__body">
                <h4 class="mb-sm">Frequently Asked Questions</h4>
                <div class="mb-lg">
                    <details class="mb-sm border border-neutral-200 rounded-sm p-sm">
                        <summary class="font-medium cursor-pointer">How do I submit an assignment?</summary>
                        <p class="mt-sm">Go to the "Submit Assignment" section, select your course, upload your file, and click submit. Make sure to check the file requirements before uploading.</p>
                    </details>
                    
                    <details class="mb-sm border border-neutral-200 rounded-sm p-sm">
                        <summary class="font-medium cursor-pointer">Where can I view my grades?</summary>
                        <p class="mt-sm">Grades are available in the "Academic Records" section of your profile. They are typically updated within 7 days after assignment submission.</p>
                    </details>
                    
                    <details class="mb-sm border border-neutral-200 rounded-sm p-sm">
                        <summary class="font-medium cursor-pointer">How do I pay my tuition fees?</summary>
                        <p class="mt-sm">You can pay your fees through the "Pay Fees" option in the dashboard. Multiple payment methods are available including credit card and bank transfer.</p>
                    </details>
                </div>
                
                <h4 class="mb-sm">Contact Support</h4>
                <p class="mb-md">For additional help, please contact our support team:</p>
                <ul class="mb-lg" style="list-style-type: none;">
                    <li class="mb-sm flex items-center gap-sm">
                        <i class="fas fa-envelope text-neutral-500"></i>
                        <span>support@studentportal.edu</span>
                    </li>
                    <li class="mb-sm flex items-center gap-sm">
                        <i class="fas fa-phone text-neutral-500"></i>
                        <span>(123) 456-7890</span>
                    </li>
                    <li class="flex items-center gap-sm">
                        <i class="fas fa-clock text-neutral-500"></i>
                        <span>Monday-Friday, 9am-5pm</span>
                    </li>
                </ul>
            </div>
            <div class="modal__footer">
                <button class="btn btn--primary" onclick="hideModal('helpModal')">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Modal functions
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('modal--active');
            document.body.style.overflow = 'hidden';
        }
        
        function hideModal(modalId) {
            document.getElementById(modalId).classList.remove('modal--active');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('modal--active');
                document.body.style.overflow = 'auto';
            }
        }
        
        // Help button event listener
        document.getElementById('helpBtn').addEventListener('click', function() {
            showModal('helpModal');
        });
    </script>
</body>
</html>