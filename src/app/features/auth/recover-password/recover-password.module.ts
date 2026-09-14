import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';

import { RecoverPasswordPageRoutingModule } from './recover-password-routing.module';
import { RecoverPasswordPage } from './recover-password.page';
import { SharedModule } from '../../../shared/shared-module';

@NgModule({
  imports: [
    CommonModule,
    IonicModule,
    RecoverPasswordPageRoutingModule,
    SharedModule,
  ],
  declarations: [RecoverPasswordPage],
})
export class RecoverPasswordPageModule {}
