window.addEventListener('load', function () {
  setTimeout(async function () {
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;

    function showMessage(msg) {
      document.body.innerHTML = `
        <div style="
          position: fixed;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          background: #fff3cd;
          color: #856404;
          padding: 30px 40px;
          max-width: 600px;
          width: 90%;
          border: 1px solid #ffeeba;
          border-radius: 10px;
          text-align: center;
          font-family: Arial, sans-serif;
          z-index: 9999;
          box-shadow: 0 0 10px rgba(0,0,0,0.2);
        ">
          ${msg}
          <br><br>
          <button onclick="location.reload()" style="
            background-color: #856404;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
          ">🔄 Refresh Page</button>
        </div>
      `;
    }

    // STEP 1: Offline detection
    if (!navigator.onLine) {
      showMessage("⚠️ <strong>No internet connection</strong><br><br>Please check your internet and try again.");
      return;
    }

    // STEP 2: Slow connection detection
    if (connection) {
      const type = connection.effectiveType;
      console.log("Effective connection type:", type);

      if (type === 'slow-2g' || type === '2g' || type === '3g') {
        showMessage(`⚠️ <strong>Your internet is slow (${type.toUpperCase()})</strong><br><br>For better performance, please switch to a faster network (Wi-Fi or 4G+).`);
        return;
      }
    }

    // STEP 3: Ad blocker detection
    try {
      await fetch("https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4182308742558451", {
        method: "HEAD",
        mode: "no-cors",
        cache: "no-store"
      });
      console.log("AdSense script likely loaded.");
    } catch (e) {
      showMessage("⚠️ <strong>Please disable your ad blocker</strong><br><br>Ads help us keep this game free for everyone.<br><br>After disabling, please reload this page.");
    }

  }, 1000);
});