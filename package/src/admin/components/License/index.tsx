import { Button, TextControl, Spinner, Flex } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { colors } from '@/admin/const/colors';
import { selectors } from '@/editor/store/selectors';
import { css } from '@/utils/css';

export const License = () => {
  const [license, setLicense] = useState(null);
  const [licenseActive, setLicenseActive] = useState(false);
  const [licenseLoading, setLicenseLoading] = useState(false);
  const [licenseError, setLicenseError] = useState(false);
  const blockstudio = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getBlockstudio(),
    [],
  );

  useEffect(() => {
    if (
      blockstudio.plugin.licenseStatus === 'valid' &&
      blockstudio.plugin.licenseCode
    ) {
      setLicenseActive(true);
      setLicense(blockstudio.plugin.licenseCode);
    }

    setTimeout(() => {
      document.body.classList.add('fabrikat-plugin-loaded');
    });
  }, []);

  const queryLicense = (action: string, license: string) => {
    setLicenseLoading(true);

    fetch(blockstudio.ajax, {
      method: 'POST',
      credentials: 'same-origin',
      headers: new Headers({
        'Content-Type': 'application/x-www-form-urlencoded',
      }),
      body: `action=${blockstudio.plugin.name}LicenseQuery&type=${action}&license=${license}&nonce=${blockstudio.nonce}`,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.license === 'valid') {
          setLicenseActive(true);
          setLicense(license);
          setLicenseError(false);
        } else if (data.license === 'invalid') {
          setLicenseActive(false);
          setLicense('');
          setLicenseError(true);
        } else {
          setLicenseActive(false);
          setLicense('');
          setLicenseError(false);
        }

        setLicenseLoading(false);
      });
  };

  const toggleLicense = () => {
    if (licenseActive) {
      queryLicense('deactivate_license', license);
    } else {
      queryLicense('activate_license', license);
    }
  };

  return (
    <div>
      {licenseActive ? (
        <TextControl
          type="password"
          value={license}
          onChange={() => {}}
          disabled
        />
      ) : (
        <TextControl
          type="text"
          placeholder="Your license key"
          onChange={setLicense}
          value={license}
        />
      )}
      <Flex align="center" justify="space-between">
        {licenseActive && !licenseError && (
          <span
            css={css({
              fontWeight: 600,
              color: '#4caf50',
            })}
          >
            License activated
          </span>
        )}
        {!licenseActive && licenseError && (
          <span
            css={css({
              fontWeight: 600,
              color: colors.error,
            })}
          >
            License invalid
          </span>
        )}
        <div
          css={css({
            marginTop: '12px',
            display: 'flex',
            alignItems: 'center',
            marginLeft: 'auto',
          })}
        >
          <Button onClick={toggleLicense} variant="secondary">
            {licenseActive ? 'Deactivate license' : 'Activate license'}
          </Button>
          {licenseLoading && (
            <Spinner
              css={css({
                marginTop: 0,
              })}
            />
          )}
        </div>
      </Flex>
    </div>
  );
};
