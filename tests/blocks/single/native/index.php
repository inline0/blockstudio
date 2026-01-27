<div class="blockstudio-test__block" id="<?php echo str_replace( "/", "-", $b["name"] ); ?>">
    <h1>ID: <?php echo str_replace( "/", "-", $b["name"] ); ?></h1>
        <div class="blockstudio-test__block-fields">
                <h1>Message Inside: <?php echo bs_get_group( $a, "group" )["message"]; ?></h1>
                        <h2>Message Outside: <?php echo $a["message"]; ?></h2>
                                <div class="<?php echo bs_get_scoped_class( $block["name"] ); ?>">
                                            <h2>Scoped H2</h2>
                                                    </div>
                                                        </div>
                                                            <code>Attributes: <?php echo json_encode( $a ); ?></code>
                                                                <code>Block: <?php echo json_encode( $b ); ?></code>
                                                                </div>
                                                                