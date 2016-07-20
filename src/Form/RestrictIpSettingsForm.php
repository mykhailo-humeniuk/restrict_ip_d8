<?php

namespace Drupal\restrict_ip\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Page callback function for admin/config/people/restrict_ip.
 */
class RestrictIpSettingsForm extends ConfigFormBase {

  /**
   * The restrict ip settings.
   *
   * @var \Drupal\restrict_ip\Form\RestrictIpSettingsForm
   */
  protected $restrictIpSettings;

  /**
   * Constructs a \Drupal\postal_code\Form\PostalCodeSettingsFor object.
   *
   */
  public function __construct() {
    $this->restrictIpSettings = $this->config('restrict_ip.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'restrict_ip_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['restrict_ip.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
//    form_load_include($form_state, 'inc', 'restrict_ip', 'includes/restrict_ip.pages');

    $form['restrict_ip_address_description'] = array(
      '#markup' => $this->t('Enter the list of allowed IP addresses below'),
      '#prefix' => '<h2>',
      '#suffix' => '</h2><p><strong style="color:red">' . t("Warning: If you don't enter your current IP address into the list, you will immediately be locked out of the system upon save, and will not be able to access the system until you are in a location with an allowed IP address. Alternatively you can allow Restrict IP to be bypassed by role, and set at least one of your roles to be bypassed on the !permissions page.", array('!permissions' => user_access('administer permissions') ? l(t('permissions'), 'admin/people/permissions') : $this->t('permissions'))) . '</strong></p><p><strong>' . $this->t('Your current IP address is: !ip_address', array('!ip_address' => '<em>' . ip_address() . '</em>')) . '</strong></p>',
    );

    $form['restrict_ip_address_list'] = array(
      '#title' => $this->t('Allowed IP Address List'),
      '#description' => $this->t('Enter the list of IP Addresses that are allowed to access the site. If this field is left empty, all IP addresses will be able to access the site. Enter one IP address per line. You may also enter a range of IP addresses in the format AAA.BBB.CCC.XXX - AAA.BBB.CCC.YYY'),
      '#type' => 'textarea',
      '#default_value' => $this->restrictIpSettings->get('restrict_ip_address_list'),
    );

    $form['restrict_ip_mail_address'] = array(
      '#title' => $this->t('Email Address'),
      '#type' => 'textfield',
      '#description' => $this->t('If you would like to include a contact email address in the error message that is shown to users that do not have an allowed IP address, enter the email address here.'),
      '#default_value' => trim($this->restrictIpSettings->get('restrict_ip_mail_address')),
    );

    if (\Drupal::moduleHandler()->moduleExists('dblog')) {
      $form['restrict_ip_watchdog'] = array(
        '#title' => $this->t('Log access attempts to watchdog'),
        '#type' => 'checkbox',
        '#default_value' => $this->restrictIpSettings->get('restrict_ip_watchdog'),
        '#description' => $this->t('When this box is checked, attempts to access the site will be logged to the Drupal log (Recent log entries)'),
      );
    }

    $form['restrict_ip_allow_role_bypass'] = array(
      '#title' => $this->t('Allow restrict IP to be bypassed by role'),
      '#type' => 'checkbox',
      '#default_value' => $this->restrictIpSettings->get('restrict_ip_allow_role_bypass'),
      '#description' => $this->t('When this box is checked, the permission "Bypass IP Restriction" will become available on the site !permissions page', array('!permissions' => user_access('administer permissions') ? l(t('permissions'), 'admin/people/permissions') : $this->t('permissions'))),
    );

    $form['restrict_ip_login_link_denied_page'] = array(
      '#title' => $this->t('Provide a link to the login page'),
      '#type' => 'checkbox',
      '#default_value' => $this->restrictIpSettings->get('restrict_ip_login_link_denied_page'),
      '#description' => $this->t('When this box is checked, a link to the login page will be provided to users who have been blocked by IP, so that they can sign in. If their role allows it, they will then be given access to the site.'),
      '#states' => array(
        'visible' => array(
          '#edit-restrict-ip-allow-role-bypass' => array('checked' => TRUE),
        ),
      ),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Validation function for restrict_ip_settings()
   *
   * This function determines whether or not the values entered
   * in whitelisted IPs list are valid IP addresses
   *
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $ip_addresses = restrict_ip_sanitize_ip_list($form_state->getValue('restrict_ip_address_list'));
    if (count($ip_addresses)) {
      foreach ($ip_addresses as $ip_address) {
        if ($ip_address != '::1') {
          // Check if IP address is a valid singular IP address (ie - not a range)
          if (!preg_match('~^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$~', trim($ip_address))) {
            // IP address is not a single IP address, so we need to check if it's a range of addresses
            $pieces = explode('-', $ip_address);
            // We only need to continue checking this IP address
            // if it is a range of addresses
            if (count($pieces) == 2) {
              $start_ip = trim($pieces[0]);
              if (!preg_match('~^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$~', $start_ip)) {
                $form_state->setErrorByName('restrict_ip_address_list', $this->t('@ip_address is not a valid IP address.', array('@ip_address' => $start_ip)));
              }
              else {
                $start_pieces = explode('.', $start_ip);
                $start_final_chunk = (int) array_pop($start_pieces);
                $end_ip = trim($pieces[1]);
                $end_valid = TRUE;
                if (preg_match('~^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$~', $end_ip)) {
                  $end_valid = TRUE;
                  $end_pieces = explode('.', $end_ip);
                  for ($i = 0; $i < 3; $i++) {
                    if ((int) $start_pieces[$i] != (int) $end_pieces[$i]) {
                      $end_valid = FALSE;
                    }
                  }
                  if ($end_valid) {
                    $end_final_chunk = (int) array_pop($end_pieces);
                    if ($start_final_chunk > $end_final_chunk) {
                      $end_valid = FALSE;
                    }
                  }
                }
                elseif (!is_numeric($end_ip)) {
                  $end_valid = FALSE;
                }
                else {
                  if ($end_ip > 255) {
                    $end_valid = FALSE;
                  }
                  else {
                    $start_final_chunk = array_pop($start_pieces);
                    if ($start_final_chunk > $end_ip) {
                      $end_valid = FALSE;
                    }
                  }
                }

                if (!$end_valid) {
                  $form_state->setErrorByName('restrict_ip_address_list', $this->t('@range is not a valid IP address range.', array('@range' => $ip_address)));
                }
              }
            }
            else {
              $form_state->setErrorByName('restrict_ip_address_list', $this->t('!ip_address is not a valid IP address or range of addresses.', array('!ip_address' => $ip_address)));
            }
          }
        }
      }
    }
  }
}