<?php
?>
<footer class="bg-dark text-white mt-5">
    <div class="container py-4">
        <div class="row">
            <div class="col-md-6 mb-3">
                <h5>Natural Power</h5>
                <p>Refresca tu día con nuestros jugos y smoothies 100% naturales.</p>
            </div>

            <div class="col-md-3 mb-3">
                <h6>Enlaces</h6>
                <ul class="list-unstyled">
                    <li><a href="jugos.php" class="text-white text-decoration-none">Jugos naturales</a></li>
                    <li><a href="smoothies.php" class="text-white text-decoration-none">Smoothies</a></li>
                    <li><a href="carrito.php" class="text-white text-decoration-none">Carrito</a></li>
                    <li><a href="compras.php" class="text-white text-decoration-none">Compras</a></li>
                    <li><a href="login_empleado.php" class="text-white text-decoration-none">Admin</a></li>
                </ul>
            </div>
            
            <div class="col-md-3 mb-3">
                <h6>Cuenta</h6>
                <ul class="list-unstyled">
                    <?php if (isset($_SESSION['cliente']) || isset($_SESSION['usuario'])): ?>
                        <li><a href="logout_cliente.php" class="text-white text-decoration-none">Cerrar sesión</a></li>
                    <?php else: ?>
                        <li><a href="login_cliente.php" class="text-white text-decoration-none">Iniciar sesión</a></li>
                        <li><a href="registro_cliente.php" class="text-white text-decoration-none">Registrarse</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="text-center pt-3 border-top border-secondary">
            &copy; <?= date('Y') ?> Natural Power. Todos los derechos reservados.
        </div>
    </div>
</footer>