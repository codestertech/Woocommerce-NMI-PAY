
<label>Credit Card Number</label>
<div id="ccnumber"></div>
<label>CC EXP</label>
<div id="ccexp"></div>
<label>CVV</label>
<div id="cvv"></div>
<br>
<br>
<button style="margin-top: 2%;" id="payButton">Pay Now</button>

<div id="threeDSMountPoint"></div>
<script src="https://secure.nmi.com/js/v1/Gateway.js"></script>
<script
        src="https://secure.nmi.com/token/Collect.js"
        data-tokenization-key="72pW65-t68XeA-Fe5Dx5-2s78hB"
></script>
<script>
    const gateway = Gateway.create('');
    const threeDS = gateway.get3DSecure();

    window.addEventListener('DOMContentLoaded', () => {
        CollectJS.configure({
            variant: 'inline',
            callback: (e) => {
                const options = {
                    paymentToken: e.token,
                    currency: 'GBP',
                    amount: '1000',
                    email: 'none@example.com',
                    phone: '8008675309',
                    city: 'New York',
                    state: 'NY',
                    address1: '123 Fist St.',
                    country: 'US',
                    firstName: 'John',
                    lastName: 'Doe',
                    postalCode: '60001'
                };

                const threeDSecureInterface = threeDS.createUI(options);
                threeDSecureInterface.start('#threeDSMountPoint');

                threeDSecureInterface.on('challenge', function(e) {
                    console.log('Challenged ->');
                });

                threeDSecureInterface.on('complete', function(e) {
                    console.log("Completed::  ", e);

                    fetch('direct-post-back-end.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            ...options,
                            cavv: e.cavv,
                            xid: e.xid,
                            eci: e.eci,
                            cardHolderAuth: e.cardHolderAuth,
                            threeDsVersion: e.threeDsVersion,
                            directoryServerId: e.directoryServerId,
                        })
                    })
                });

                threeDSecureInterface.on('failure', function(e) {
                    console.log('failure');
                    console.log(e);
                });
            }
        })

        gateway.on('error', function (e) {
            console.error(e);
        })
    })
</script>

