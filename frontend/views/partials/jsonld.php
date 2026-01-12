<?php
// frontend/views/partials/jsonld.php
// Expect $jsonLd (array) in scope
if (!empty($jsonLd) && is_array($jsonLd)) {
  echo '<script type="application/ld+json">'.json_encode($jsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
}
