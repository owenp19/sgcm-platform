import { Component, OnDestroy, OnInit } from '@angular/core';
import { NavController, MenuController } from '@ionic/angular';
import { TranslationService } from '../../services/translation.service';
import { addIcons } from 'ionicons';
import {
  menuOutline,
  notificationsOutline,
  chevronForwardOutline,
  searchOutline,
  optionsOutline,
  homeOutline,
  documentTextOutline,
  folderOpenOutline,
  personOutline,
  helpCircleOutline,
  closeOutline,
  idCardOutline,
  briefcaseOutline,
  cardOutline,
  peopleOutline,
  airplaneOutline,
  businessOutline,
  settingsOutline,
  logOutOutline,
  informationCircleOutline,
  calendarOutline,
  cloudDownloadOutline,
  shieldCheckmarkOutline,
  arrowForwardOutline,
} from 'ionicons/icons';
import { AuthService } from '../../services/auth.service';
import { NotificationsService } from '../../services/notifications.service';
import { AppInfoService } from '../../services/app-info.service';
import { environment } from '../../../environments/environment';

interface HeroSlide {
  image: string;
  kicker: string;
  title: string;
  description: string;
  actionLabel: string;
  route: string;
}

interface MenuOption {
  label: string;
  icon: string;
  route: string;
}

interface UserProfile {
  firstName: string;
  lastName: string;
  photoUrl: string | null;
}

interface OccreProcedure {
  title: string;
  description: string;
  icon: string;
  route: string;
  colorClass: string;
  category: string;
  slug: string;
}

@Component({
  selector: 'app-home',
  templateUrl: './home.page.html',
  styleUrls: ['./home.page.scss'],
  standalone: false,
})
export class HomePage implements OnInit, OnDestroy {
  public readonly brandImagePath = 'assets/images/logo-occre.png';

  public currentUser: UserProfile = {
    firstName: '',
    lastName: '',
    photoUrl: null,
  };

  public notificationCount = 0;

  public updateAvailable = false;
  public appVersion = '';
  public latestVersion = '';

  public activeSlideIndex = 0;
  public searchTerm = '';

  public get heroSlides(): HeroSlide[] {
    const t = (key: string) => this.translation.translate(key);
    return [
      {
        image: 'assets/images/welcome-bg-1.jpg',
        kicker: t('home.hero.welcome_kicker'),
        title: t('home.hero.welcome_title'),
        description: t('home.hero.welcome_desc'),
        actionLabel: t('home.hero.welcome_action'),
        route: '/procedures',
      },
      {
        image: 'assets/images/welcome-bg-2.jpg',
        kicker: t('home.hero.documents_kicker'),
        title: t('home.hero.documents_title'),
        description: t('home.hero.documents_desc'),
        actionLabel: t('home.hero.documents_action'),
        route: '/procedures',
      },
      {
        image: 'assets/images/welcome-bg-3.jpg',
        kicker: t('home.hero.tracking_kicker'),
        title: t('home.hero.tracking_title'),
        description: t('home.hero.tracking_desc'),
        actionLabel: t('home.hero.tracking_action'),
        route: '/procedures',
      },
    ];
  }

  public menuOptions: MenuOption[] = [];

  private buildMenuOptions(): void {
    const t = (key: string) => this.translation.translate(key);
    this.menuOptions = [
      { label: t('menu.myprocedures'), icon: 'folder-open-outline', route: '/my-procedures' },
      { label: t('menu.recordsearch'), icon: 'search-outline', route: '/record-search' },
      { label: t('menu.appointments'), icon: 'calendar-outline', route: '/appointments' },
      { label: t('menu.notifications'), icon: 'notifications-outline', route: '/notifications' },
      { label: t('menu.help'), icon: 'help-circle-outline', route: '/help-contact' },
      { label: t('menu.legal'), icon: 'information-circle-outline', route: '/legal' },
      { label: t('menu.settings'), icon: 'settings-outline', route: '/settings' },
      { label: t('menu.logout'), icon: 'log-out-outline', route: 'logout' },
    ];
  }

  public readonly occreProcedures: OccreProcedure[] = [
    {
      title: 'Mi Primera Tarjeta OCCRE',
      description:
        'Solicitud inicial de tarjeta de residencia para residentes del Archipiélago.',
      icon: 'id-card-outline',
      route: '/procedures',
      colorClass: 'is-blue',
      category: 'Residencia',
      slug: 'mi-primera-tarjeta-occre',
    },
    {
      title: 'Cambio de Tarjeta de Identidad a Cédula',
      description:
        'Actualización del documento de identidad asociado a la Tarjeta OCCRE.',
      icon: 'person-outline',
      route: '/procedures',
      colorClass: 'is-green',
      category: 'Actualización',
      slug: 'cambio-tarjeta-identidad-cedula',
    },
    {
      title: 'Requisitos para Inversionistas',
      description:
        'Información documental para personas o empresas inversionistas.',
      icon: 'briefcase-outline',
      route: '/procedures',
      colorClass: 'is-orange',
      category: 'Inversionistas',
      slug: 'requisitos-inversionistas',
    },
    {
      title: 'Duplicado Tarjeta OCCRE',
      description:
        'Solicitud de duplicado por pérdida, deterioro u otra causal.',
      icon: 'card-outline',
      route: '/procedures',
      colorClass: 'is-purple',
      category: 'Duplicado',
      slug: 'duplicado-tarjeta-occre',
    },
    {
      title: 'Independiente Primera Vez',
      description:
        'Checklist para solicitantes independientes que realizan el trámite inicial.',
      icon: 'document-text-outline',
      route: '/procedures',
      colorClass: 'is-blue',
      category: 'Residencia',
      slug: 'independiente-primera-vez',
    },
    {
      title: 'Residencia por Convivencia',
      description:
        'Trámite para beneficiario y otorgante por vínculo de convivencia.',
      icon: 'people-outline',
      route: '/procedures',
      colorClass: 'is-orange',
      category: 'Convivencia',
      slug: 'residencia-convivencia',
    },
    {
      title: 'Pasajero en Comisión',
      description:
        'Orientación para comisión temporal, entidad solicitante y familiares.',
      icon: 'airplane-outline',
      route: '/procedures',
      colorClass: 'is-purple',
      category: 'Comisión',
      slug: 'pasajero-comision',
    },
    {
      title: 'Trabajador Foráneo o Trámite Especial',
      description:
        'Requisitos para trabajador foráneo, empresa contratante y núcleo familiar.',
      icon: 'business-outline',
      route: '/procedures',
      colorClass: 'is-blue',
      category: 'Trabajador foráneo',
      slug: 'trabajador-foraneo-tramite-especial',
    },
  ];

  private carouselInterval?: ReturnType<typeof setInterval>;

  constructor(
    private readonly navCtrl: NavController,
    private readonly menuController: MenuController,
    private readonly authService: AuthService,
    private readonly notificationsService: NotificationsService,
    private readonly appInfo: AppInfoService,
    private readonly translation: TranslationService,
  ) {
    addIcons({
      menuOutline,
      notificationsOutline,
      chevronForwardOutline,
      searchOutline,
      optionsOutline,
      homeOutline,
      documentTextOutline,
      folderOpenOutline,
      personOutline,
      helpCircleOutline,
      closeOutline,
      idCardOutline,
      briefcaseOutline,
      cardOutline,
      peopleOutline,
      airplaneOutline,
      businessOutline,
      settingsOutline,
      logOutOutline,
      informationCircleOutline,
      calendarOutline,
      cloudDownloadOutline,
      shieldCheckmarkOutline,
      arrowForwardOutline,
    });
    this.buildMenuOptions();
  }

  public ngOnInit(): void {
    const user = this.authService.getUser();
    if (user) {
      this.currentUser = {
        firstName: user.firstName,
        lastName: user.lastName,
        photoUrl: user.photoUrl || null,
      };
      if (!environment.useFallback) {
        this.notificationsService.getUnreadCount().subscribe({
          next: (res) => { this.notificationCount = res.count; },
          error: () => { this.notificationCount = 0; },
        });
      }
    }
    this.startCarousel();
    this.translation.onChange(() => this.buildMenuOptions());
    void this.checkForUpdates();
  }

  private async checkForUpdates(): Promise<void> {
    if (!this.appInfo.isNative()) return;
    try {
      const [local, server] = await Promise.all([
        this.appInfo.getAppVersion(),
        this.appInfo.getServerVersion(),
      ]);
      this.appVersion = local;
      if (server?.version) {
        this.latestVersion = server.version;
        this.updateAvailable = this.appInfo.compareVersions(local, server.version) < 0;
      }
    } catch {
      this.updateAvailable = false;
    }
  }

  public ngOnDestroy(): void {
    this.stopCarousel();
  }

  public get filteredProcedures(): OccreProcedure[] {
    const query = this.searchTerm.trim().toLowerCase();
    if (!query) {
      return this.occreProcedures;
    }
    return this.occreProcedures.filter(
      (p) =>
        p.title.toLowerCase().includes(query) ||
        p.description.toLowerCase().includes(query) ||
        p.category.toLowerCase().includes(query)
    );
  }

  public get activeSlide(): HeroSlide {
    return this.heroSlides[this.activeSlideIndex];
  }

  public get userInitials(): string {
    return this.buildInitials(
      this.currentUser.firstName,
      this.currentUser.lastName
    );
  }

  public get profileImageUrl(): string | null {
    return this.currentUser.photoUrl;
  }

  public async openMenu(): Promise<void> {
    await this.menuController.open('home-menu');
  }

  public async closeMenu(): Promise<void> {
    await this.menuController.close('home-menu');
  }

  public async goTo(route: string): Promise<void> {
    if (route === 'logout') {
      await this.closeMenu();
      this.authService.logout();
      return;
    }
    await this.closeMenu();
    await this.navCtrl.navigateForward(route);
  }

  public goToActiveSlideRoute(): void {
    void this.navCtrl.navigateForward(this.activeSlide.route);
  }

  public selectSlide(index: number): void {
    if (index < 0 || index >= this.heroSlides.length) {
      return;
    }

    this.activeSlideIndex = index;
    this.restartCarousel();
  }

  public openNotifications(): void {
    void this.navCtrl.navigateForward('/notifications');
  }

  public openProfile(): void {
    void this.navCtrl.navigateForward('/profile');
  }

  public goToProcedure(procedure: OccreProcedure): void {
    void this.navCtrl.navigateForward('/procedures/' + procedure.slug);
  }

  public async openAppStore(): Promise<void> {
    try {
      const { Browser } = await import('@capacitor/browser');
      await Browser.open({ url: 'https://play.google.com/store' });
    } catch {
      this.updateAvailable = false;
    }
  }

  private startCarousel(): void {
    this.stopCarousel();

    if (this.heroSlides.length <= 1) {
      return;
    }

    this.carouselInterval = setInterval(() => {
      this.activeSlideIndex =
        (this.activeSlideIndex + 1) % this.heroSlides.length;
    }, 6000);
  }

  private stopCarousel(): void {
    if (!this.carouselInterval) {
      return;
    }

    clearInterval(this.carouselInterval);
    this.carouselInterval = undefined;
  }

  private restartCarousel(): void {
    this.stopCarousel();
    this.startCarousel();
  }

  private buildInitials(firstName: string, lastName: string): string {
    const firstInitial = firstName.trim().charAt(0);
    const lastInitial = lastName.trim().charAt(0);

    if (!firstInitial && !lastInitial) {
      return 'US';
    }

    return `${firstInitial}${lastInitial}`.toUpperCase();
  }
}