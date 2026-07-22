<?php
$defaults=[
'body_bg'=>'#F7F9FC','topbar_bg'=>'#FFFFFF','topbar_text'=>'#0F172A','card_bg'=>'#FFFFFF','card_header_bg'=>'#FFFFFF','border_soft'=>'#E2E8F0','text_main'=>'#0F172A','text_muted'=>'#64748B',
'sidebar_bg_1'=>'#243447','sidebar_bg_2'=>'#2F3A45','sidebar_bg_3'=>'#1F2933','sidebar_text'=>'#FFFFFF','sidebar_active_bg_1'=>'#3B82F6','sidebar_active_bg_2'=>'#2563EB','sidebar_active_text'=>'#FFFFFF','sidebar_hover_bg'=>'rgba(255,255,255,.10)','sidebar_hover_text'=>'#FFFFFF','sidebar_submenu_bg'=>'rgba(255,255,255,.06)',
'brand_1'=>'#0F766E','brand_2'=>'#2563EB','brand_text'=>'#FFFFFF','table_header_bg'=>'#EEF2F7','table_header_text'=>'#334155','table_row_hover'=>'#F8FAFC','input_bg'=>'#FFFFFF','input_border'=>'#CBD5E1','input_text'=>'#0F172A','success_color'=>'#16A34A','warning_color'=>'#F59E0B','danger_color'=>'#DC2626','info_color'=>'#2563EB',
'font_family'=>'Inter, Arial, sans-serif','base_font_size'=>'14','heading_font_size'=>'24','font_weight'=>'500','heading_font_weight'=>'800','line_height'=>'1.5','letter_spacing'=>'0','button_text_transform'=>'none',
'card_radius'=>'18','button_radius'=>'12','sidebar_width'=>'268','navbar_height'=>'64','page_spacing'=>'16','sidebar_style'=>'gradient','navbar_style'=>'solid','card_style'=>'elevated','button_style'=>'rounded','table_style'=>'clean','table_density'=>'comfortable','theme_mode'=>'light','layout_width'=>'fluid','content_density'=>'comfortable'
];
$colors=$defaults;$businessId=(int)current_business_id();
if(isset($conn)&&table_exists($conn,'website_color_settings')){
 $has=table_has_column($conn,'website_color_settings','business_id');
 if($has&&$businessId>0){$s=mysqli_prepare($conn,"SELECT setting_key,setting_value FROM website_color_settings WHERE is_active=1 AND (business_id=? OR business_id IS NULL) ORDER BY business_id ASC");mysqli_stmt_bind_param($s,'i',$businessId);mysqli_stmt_execute($s);$r=mysqli_stmt_get_result($s);}
 else{$r=mysqli_query($conn,"SELECT setting_key,setting_value FROM website_color_settings WHERE is_active=1");}
 if($r)while($row=mysqli_fetch_assoc($r))if(array_key_exists($row['setting_key'],$colors))$colors[$row['setting_key']]=$row['setting_value'];
}
function cssv($a,$k){return htmlspecialchars((string)($a[$k]??''),ENT_QUOTES,'UTF-8');}
?>
<style>
:root{
<?php foreach(['body_bg','topbar_bg','topbar_text','card_bg','card_header_bg','border_soft','text_main','text_muted','sidebar_bg_1','sidebar_bg_2','sidebar_bg_3','sidebar_text','sidebar_active_bg_1','sidebar_active_bg_2','sidebar_active_text','sidebar_hover_bg','sidebar_hover_text','sidebar_submenu_bg','brand_1','brand_2','brand_text','table_header_bg','table_header_text','table_row_hover','input_bg','input_border','input_text','success_color','warning_color','danger_color','info_color'] as $k): ?>--<?=str_replace('_','-',$k)?>:<?=cssv($colors,$k)?>;
<?php endforeach; ?>
--sidebar-bg:linear-gradient(180deg,var(--sidebar-bg-1),var(--sidebar-bg-2),var(--sidebar-bg-3));
--app-font-family:<?=cssv($colors,'font_family')?>;--app-font-size:<?=cssv($colors,'base_font_size')?>px;--app-heading-size:<?=cssv($colors,'heading_font_size')?>px;--app-font-weight:<?=cssv($colors,'font_weight')?>;--app-heading-weight:<?=cssv($colors,'heading_font_weight')?>;--app-line-height:<?=cssv($colors,'line_height')?>;--app-letter-spacing:<?=cssv($colors,'letter_spacing')?>px;--app-button-transform:<?=cssv($colors,'button_text_transform')?>;
--card-radius:<?=cssv($colors,'card_radius')?>px;--button-radius:<?=cssv($colors,'button_radius')?>px;--sidebar-width:<?=cssv($colors,'sidebar_width')?>px;--navbar-height:<?=cssv($colors,'navbar_height')?>px;--page-spacing:<?=cssv($colors,'page_spacing')?>px;--shadow-card:0 18px 45px rgba(15,23,42,.08);
}
</style>
<script>
document.documentElement.dataset.themeMode=<?=json_encode($colors['theme_mode'])?>;
document.documentElement.dataset.theme=<?=json_encode($colors['theme_mode']==='dark'?'dark':'light')?>;
document.documentElement.dataset.sidebarStyle=<?=json_encode($colors['sidebar_style'])?>;
document.documentElement.dataset.navbarStyle=<?=json_encode($colors['navbar_style'])?>;
document.documentElement.dataset.cardStyle=<?=json_encode($colors['card_style'])?>;
document.documentElement.dataset.buttonStyle=<?=json_encode($colors['button_style'])?>;
document.documentElement.dataset.tableStyle=<?=json_encode($colors['table_style'])?>;
document.documentElement.dataset.tableDensity=<?=json_encode($colors['table_density'])?>;
document.documentElement.dataset.layoutWidth=<?=json_encode($colors['layout_width'])?>;
document.documentElement.dataset.contentDensity=<?=json_encode($colors['content_density'])?>;
</script>
