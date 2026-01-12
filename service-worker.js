/* Compatibility Service Worker wrapper (Godyar)
 * - Keeps older registrations working (/service-worker.js)
 * - Loads the real worker from /sw.js
 */
importScripts(new URL('sw.js', self.registration.scope).toString());
