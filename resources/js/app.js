/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

window.Vue = require('vue').default;

/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */

// const files = require.context('./', true, /\.vue$/i)
// files.keys().map(key => Vue.component(key.split('/').pop().split('.')[0], files(key).default))

Vue.component('example-component', require('./components/ExampleComponent.vue').default);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = new Vue({
    el: '#app',
});



document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar-expanded');
    const collapseBtn = document.querySelector('.collapse-btn');
    const collapseIcon = collapseBtn ? collapseBtn.querySelector('i') : null;
    const mainContent = document.querySelector('.main-content'); // Selector del contenido principal, ajústalo según tu layout

    if (collapseBtn && sidebar && collapseIcon && mainContent) {
        collapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-collapsed');
            sidebar.classList.toggle('sidebar-expanded');
            collapseIcon.classList.toggle('fa-chevron-left');
            collapseIcon.classList.toggle('fa-chevron-right');

            // Opcional: Ajustar el margen del contenido principal al colapsar el sidebar
            if (mainContent) {
                mainContent.classList.toggle('margin-left-expanded');
                mainContent.classList.toggle('margin-left-collapsed');
            }
        });
    }
});