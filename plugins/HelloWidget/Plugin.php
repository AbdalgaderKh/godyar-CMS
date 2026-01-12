<?php
// plugins/HelloWidget/Plugin.php
// يجب أن يُرجع كائن يطبّق GodyarPluginInterface

return new class implements GodyarPluginInterface {

    public function register(PluginManager $pm): void
    {
        // إضافة بطاقة إلى بطاقات الداشبورد
        // سنستدعي هذا الـ hook من admin/index.php لاحقًا
        $pm->addHook('admin_dashboard_cards', [$this, 'addDashboardCard'], 20);

        // نص صغير أسفل لوحة التحكم
        $pm->addHook('admin_dashboard_after', [$this, 'renderFooterNote'], 20);
    }

    /**
     * تعديل مصفوفة البطاقات في لوحة التحكم
     * نستقبلها بالـ reference (&$cards)
     */
    public function addDashboardCard(array &$cards): void
    {
        $cards[] = [
            'title' => 'Hello من الإضافة 👋',
            'value' => date('H:i'),
            'icon'  => 'puzzle-piece',
            'color' => 'info',
            'hint'  => 'هذه بطاقة تم توليدها عبر Plugin HelloWidget.',
        ];
    }

    /**
     * نص أسفل صفحة الداشبورد
     */
    public function renderFooterNote(): void
    {
        echo '<p class="text-center text-muted mt-3 small">'
            . 'هذه الرسالة صادرة من إضافة <code>HelloWidget</code>.'
            . '</p>';
    }
};
